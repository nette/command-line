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


test('strip removes every kind of escape sequence, also one left unterminated', function () {
	Assert::same('hello', Ansi::strip("\e(Bhello\e7")); // character set, saved cursor
	Assert::same('ab', Ansi::strip("a\ePq#0;1\e\\b")); // DCS
	Assert::same('ab', Ansi::strip("a\e_payload\e\\b")); // APC
	Assert::same('ab', Ansi::strip("a\e]0;title\x07b")); // OSC ended by BEL
	Assert::same('ab', Ansi::strip("a\e[12\e[0mb")); // a CSI cut short ends at the next ESC
	Assert::same('ab', Ansi::strip("a\e[!0mb")); // a malformed CSI is swallowed up to its final byte, as a terminal ignores it
	Assert::same('ab', Ansi::strip("a\e]8;;url\e\\b")); // ST is a short sequence of its own
	Assert::same('ab', Ansi::strip("a\e]0;title\e[31mb"));
	Assert::same('a', Ansi::strip("a\e]0;title"));
	Assert::same('abc', Ansi::strip("abc\e[31"));
	Assert::same('a', Ansi::strip("a\e"));
	Assert::same('x', Ansi::strip("\ePq\x07still\e\\x")); // BEL ends only OSC
	Assert::same('věc ěščřž', Ansi::strip('věc ěščřž'));
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


test('measure knows the width from Unicode, and when unsure it measures wider', function () {
	Assert::same(2, Ansi::measure('🫠')); // newer emoji
	Assert::same(2, Ansi::measure('🇨🇿')); // a flag of two regional indicators
	Assert::same(2, Ansi::measure('❤️')); // emoji presentation of a narrow character
	Assert::same(1, Ansi::measure('❤'));
	Assert::same(2, Ansi::measure('☝🏽')); // the wide one need not be first
	Assert::same(2, Ansi::measure('👨‍👩‍👧'));
	Assert::same(1, Ansi::measure('⚠')); // narrow beside the wide ⚡
	Assert::same(2, Ansi::measure('⚡'));
	Assert::same(1, Ansi::measure('✓'));
	Assert::same(2, Ansi::measure('한'));
	Assert::same(1, Ansi::measure('…')); // East Asian Ambiguous is narrow
});


test('a control or a format character takes no column', function () {
	Assert::same(0, Ansi::measure("\t"));
	Assert::same(0, Ansi::measure("\u{200B}")); // zero width space
	Assert::same(0, Ansi::measure("\u{301}")); // a combining mark alone
	Assert::same(1, Ansi::measure("\u{600}")); // but a prepended mark is drawn
	Assert::same(2, Ansi::measure("a\r\nb"));
	Assert::same(1, Ansi::measure("\u{AD}")); // but a soft hyphen is drawn
	Assert::same(4, Ansi::measure("a\x7F\xFFbc")); // not UTF-8, a byte but a control one is a column
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
	Assert::same('ab…', Ansi::truncate("abcdef\x08\x08", 3)); // a backspace cut off cannot overwrite the ellipsis
	Assert::same('ab…', Ansi::truncate("abcdef\x07", 3)); // nor ring the bell
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
