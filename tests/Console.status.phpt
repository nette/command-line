<?php declare(strict_types=1);

// The status is drawn in place: the next output erases it and the next status draws it again below.

use Nette\CommandLine\Console;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


/** @return resource */
function stream()
{
	return fopen('php://memory', 'w+') ?: throw new RuntimeException;
}


/** @param resource $stream  what was written since the last call */
function written($stream): string
{
	rewind($stream);
	$s = (string) stream_get_contents($stream);
	ftruncate($stream, 0);
	rewind($stream);
	return $s;
}


test('it hides the cursor, draws in place and comes back to its first line', function () {
	putenv('COLUMNS=40');
	$stream = stream();
	$console = new Console($stream, colors: false, terminal: true);

	$console->setStatus('5/10 running');
	Assert::same("\e[?25l5/10 running\r", written($stream));

	$console->setStatus('6/10 running'); // the drawn one is erased first
	Assert::same("\e[J6/10 running\r", written($stream));

	$console->setStatus(['a', 'b', 'c']);
	Assert::same("\e[Ja\nb\nc\r\e[2A", written($stream));

	$console->clearStatus();
	Assert::same("\e[J\e[?25h", written($stream));
	$console->clearStatus(); // nothing is drawn any more
	Assert::same('', written($stream));
});


test('the output erases the status, which the next one draws again', function () {
	putenv('COLUMNS=40');
	$stream = stream();
	$console = new Console($stream, colors: false, terminal: true);

	$console->setStatus('working');
	written($stream);
	$console->writeLine('done');
	Assert::same("\e[J\e[?25hdone\n", written($stream));

	$console->setStatus('working'); // the cursor is hidden again
	Assert::same("\e[?25lworking\r", written($stream));
});


test('a status takes whole lines, so a half-written one is not erased with it', function () {
	putenv('COLUMNS=40');
	$stream = stream();
	$console = new Console($stream, colors: false, terminal: true);

	$console->write('no newline here');
	$console->setStatus('working'); // the line of its own comes first
	Assert::same("no newline here\n\e[?25lworking\r", written($stream));

	$console->write("and a line of its own\n");
	$console->setStatus('working'); // the text ended with a newline, so no line is opened
	Assert::same("\e[J\e[?25hand a line of its own\n\e[?25lworking\r", written($stream));
});


test('nothing to draw erases the status', function () {
	putenv('COLUMNS=40');
	$stream = stream();
	$console = new Console($stream, colors: false, terminal: true);

	$console->setStatus('working');
	written($stream);
	$console->setStatus([]);
	Assert::same("\e[J\e[?25h", written($stream));
	$console->setStatus(''); // nothing is drawn any more
	Assert::same('', written($stream));
});


test('a line too long is cut, since a wrapped one cannot be redrawn', function () {
	putenv('COLUMNS=10');
	$stream = stream();
	$console = new Console($stream, colors: false, terminal: true);
	$console->setStatus('abcdefghijklmno');
	Assert::same("\e[?25labcdefghi…\r", written($stream));
});


test('the status is drawn as it is given, whatever the colors are', function () {
	putenv('COLUMNS=40');
	$stream = stream();
	(new Console($stream, colors: false, terminal: true))->setStatus("\e[91mred\e[0m");
	Assert::same("\e[?25l\e[91mred\e[0m\r", written($stream));

	$stream = stream();
	(new Console($stream, colors: true, terminal: true))->setStatus("\e[91mred\e[0m");
	Assert::same("\e[?25l\e[91mred\e[0m\r", written($stream));
});


test('a stream that is not a terminal gets no status at all', function () {
	putenv('COLUMNS=40');
	$stream = stream();
	$console = new Console($stream, colors: false, terminal: false);
	$console->setStatus('working');
	$console->clearStatus();
	$console->writeLine('done');
	Assert::same("done\n", written($stream));
});
