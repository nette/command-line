<?php declare(strict_types=1);

use Nette\CommandLine\Ansi;
use Nette\CommandLine\Console;
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


test('a color of the whole text is given to the writing', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream, colors: true);
	$console->writeLine('done', 'green');
	$console->write('half', 'red');
	rewind($stream);
	Assert::same("\e[32mdone\e[0m\n\e[91mhalf\e[0m", stream_get_contents($stream));
});


test('the text is written as it is, so what is data stays data', function () {
	foreach ([true, false] as $colors) {
		$stream = fopen('php://memory', 'w+');
		$console = new Console($stream, colors: $colors);
		$console->write("<?php \$s = \"\e[91m\";\n"); // the code of a file is not text of the console
		rewind($stream);
		Assert::same("<?php \$s = \"\e[91m\";\n", stream_get_contents($stream));
	}
});


test('a text from elsewhere is filtered by the caller, not by the console', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream, colors: false);
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

	$console->useColors(true);
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


test('the Windows branch turns VT100 on only for a terminal', function () {
	if (PHP_OS_FAMILY !== 'Windows') {
		Tester\Environment::skip('Windows only.');
	}

	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream);
	$console->useColors(true); // must not try to switch VT100 on for a memory stream
	Assert::same("\e[91mred\e[0m", $console->color('red', 'red'));
});
