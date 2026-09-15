<?php declare(strict_types=1);

use Nette\CommandLine\Ansi;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('strip removes the escape sequences and leaves the text', function () {
	Assert::same('red', Ansi::strip("\e[91mred\e[0m"));
	Assert::same('ab', Ansi::strip("a\e[Kb"));
	Assert::same('link', Ansi::strip("\e]8;;https://nette.org\e\\link\e]8;;\e\\"));
	Assert::same('plain', Ansi::strip('plain'));
	Assert::same("a\nb", Ansi::strip("a\nb")); // a newline is not an escape sequence
});


test('measure counts the columns of the terminal, not bytes', function () {
	Assert::same(0, Ansi::measure(''));
	Assert::same(3, Ansi::measure('abc'));
	Assert::same(3, Ansi::measure("\e[91mabc\e[0m")); // an escape sequence takes no column
	Assert::same(6, Ansi::measure('příliš')); // an accented letter takes one
	Assert::same(1, Ansi::measure("e\u{301}")); // and so does one with a combining accent
	Assert::same(4, Ansi::measure('日本')); // a wide character takes two
	Assert::same(2, Ansi::measure('🍏'));
});


test('pad fills the text up to the width', function () {
	Assert::same('ab  ', Ansi::pad('ab', 4));
	Assert::same('  ab', Ansi::pad('ab', 4, STR_PAD_LEFT));
	Assert::same(' ab ', Ansi::pad('ab', 4, STR_PAD_BOTH));
	Assert::same('ab--', Ansi::pad('ab', 4, char: '-'));
	Assert::same('abcd', Ansi::pad('abcd', 2)); // already that wide
	Assert::same("\e[91mab\e[0m  ", Ansi::pad("\e[91mab\e[0m", 4));
	Assert::same('日  ', Ansi::pad('日', 4));

	Assert::exception( // a character of another width would overshoot
		fn() => Ansi::pad('ab', 4, char: '日'),
		InvalidArgumentException::class,
		"The padding character must be one column wide, '日' given.",
	);
});


test('truncate cuts the text to the width and marks the cut', function () {
	Assert::same('abcdefgh', Ansi::truncate('abcdefgh', 8));
	Assert::same('abcdefg…', Ansi::truncate('abcdefghij', 8));
	Assert::same('abc...', Ansi::truncate('abcdefghij', 6, '...'));
	Assert::same('', Ansi::truncate('abc', 0));
	Assert::same('日…', Ansi::truncate('日本語', 3)); // a wide character takes two of the three columns
});


test('an ellipsis that would not fit is left out, so nothing comes out wider than asked', function () {
	Assert::same('ab', Ansi::truncate('abcdef', 2, '...'));
	Assert::same('a', Ansi::truncate('abcdef', 1, '...'));
	Assert::same('ef', Ansi::truncate('abcdef', 2, '...', keepEnd: true));
	Assert::same('…', Ansi::truncate('abcdef', 1));
});


test('truncate keeps the end of a path, and the color stays closed', function () {
	Assert::same('…defghij', Ansi::truncate('abcdefghij', 8, keepEnd: true));
	Assert::same('…/run.php', Ansi::truncate('src/Console/run.php', 9, keepEnd: true));
	Assert::same("\e[91mabc…\e[0m", Ansi::truncate("\e[91mabcdef\e[0m", 4));
});
