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
});
