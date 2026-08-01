<?php declare(strict_types=1);

use Nette\CommandLine\{Ansi, ColorDepth, Console};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

putenv('NO_COLOR');
putenv('FORCE_COLOR');


test('write goes to the given stream', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream);
	$console->write('hello');
	$console->writeLine(' world');
	$console->writeLine();
	rewind($stream);
	Assert::same("hello world\n\n", stream_get_contents($stream));
});


test('a stream that cannot be written to is refused at once', function () {
	Assert::exception(
		fn() => new Console(fopen(__FILE__, 'r')),
		InvalidArgumentException::class,
		'The stream is not open for writing.',
	);
});


test('a color of the whole text is given to the writing', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream, colorDepth: ColorDepth::Ansi256);
	$console->writeLine('done', 'green');
	$console->write('half', 'red');
	rewind($stream);
	Assert::same("\e[32mdone\e[0m\n\e[91mhalf\e[0m", stream_get_contents($stream));
});


test('the text is written as it is, so what is data stays data', function () {
	foreach ([ColorDepth::Ansi256, ColorDepth::None] as $depth) {
		$stream = fopen('php://memory', 'w+');
		$console = new Console($stream, colorDepth: $depth);
		$console->write("<?php \$s = \"\e[91m\";\n"); // the code of a file is not text of the console
		rewind($stream);
		Assert::same("<?php \$s = \"\e[91m\";\n", stream_get_contents($stream));
	}
});


test('a text from elsewhere is filtered by the caller, not by the console', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream, colorDepth: ColorDepth::None);
	$fromSubprocess = "\e[91mred\e[0m plain\n";
	$console->write($console->hasColors() ? $fromSubprocess : Ansi::strip($fromSubprocess));
	rewind($stream);
	Assert::same("red plain\n", stream_get_contents($stream));
});


test('a stream that is not a terminal gets no colors', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream);
	Assert::false($console->isTerminal());
	Assert::false($console->hasColors());
	Assert::same('plain', $console->color('red', 'plain'));

	$console->setColorDepth(ColorDepth::Ansi256);
	Assert::true($console->hasColors());
	Assert::same("\e[91mplain\e[0m", $console->color('red', 'plain'));
});


test('COLUMNS wins and is read every time', function () {
	putenv('COLUMNS=42');
	Assert::same(42, (new Console)->getWidth());
	Assert::same(42, (new Console(fopen('php://memory', 'w+')))->getWidth());
	putenv('COLUMNS=17');
	Assert::same(17, (new Console)->getWidth());
	putenv('COLUMNS');
	Assert::same(80, (new Console)->getWidth()); // the tests are piped, so no terminal to ask
});


test('a console over a stream that is not a terminal is 80 columns wide', function () {
	putenv('COLUMNS');
	Assert::same(80, (new Console(fopen('php://memory', 'w+')))->getWidth());
});


test('COLUMNS counts only when it is a positive integer', function () {
	foreach (['-1', 'abc', '10x', '0', ' 42'] as $invalid) {
		putenv("COLUMNS=$invalid");
		Assert::same(80, (new Console)->getWidth(), $invalid);
	}

	putenv('COLUMNS');
});


test('on Windows a stream that is not a console is left alone, whatever the colors', function () {
	if (PHP_OS_FAMILY !== 'Windows') {
		Tester\Environment::skip('Windows only.');
	}

	foreach ([ColorDepth::None, ColorDepth::Ansi256] as $depth) {
		Assert::noError(fn() => new Console(fopen('php://memory', 'w+'), colorDepth: $depth));
		$filtered = fopen('php://memory', 'w+');
		stream_filter_append($filtered, 'string.rot13');
		Assert::noError(fn() => new Console($filtered, colorDepth: $depth));
	}
});
