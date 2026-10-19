<?php declare(strict_types=1);

use Nette\CommandLine\{Attributes, ColorDepth, Command, Console, Runner, UsageException};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


// runs the program over consoles writing into memory, colored unless the line turns the colors off
/** @return array{int, string, string}  the exit code, what went to the output and what to the errors */
function runRunner(Command $program, array $argv, mixed ...$settings): array
{
	[$out, $err] = [fopen('php://memory', 'w+'), fopen('php://memory', 'w+')];
	$code = (new Runner(
		$program,
		...$settings,
		out: new Console($out, ColorDepth::Ansi16),
		err: new Console($err, ColorDepth::Ansi16),
	))->run($argv);
	rewind($out);
	rewind($err);
	return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
}


function stripColors(string $text): string
{
	return (string) preg_replace('~\e\[[\d;]*m~', '', $text);
}


test('the handler of the command the line selects runs, and its exit code is returned', function () {
	$program = Command::from(#[Attributes\Command(name: 'tool')] fn(#[Attributes\Argument] string $file) => strlen($file));
	Assert::same([4, '', ''], runRunner($program, ['a.md']));
});


test('--help and -h print the help of the command the line selects, the version above it', function () {
	$program = new Command('tool');
	$program->addCommand('check', 'Check the files', handler: fn() => 1)->addArgument('paths', repeatable: true);
	[$code, $out, $err] = runRunner($program, ['check', '--help'], version: 'Tool 1.0');
	Assert::same(0, $code);
	Assert::same('', $err);
	Assert::match("Tool 1.0\n\nUsage: tool check [options] <paths>...\n\nCheck the files\n%A%", stripColors($out));
	Assert::match("Usage: tool [options] [command]\n%A%-h, --help%A%", stripColors(runRunner($program, ['-h'])[1]));
});


test('--version prints the version, a closure writing it for the console it is written to', function () {
	$program = Command::from(#[Attributes\Command(name: 'tool')] fn() => 1);
	Assert::same([0, "Tool 1.0\n", ''], runRunner($program, ['-V'], version: 'Tool 1.0'));
	[$code, $out] = runRunner($program, ['--version'], version: fn(Console $console) => $console->color('red', 'Tool'));
	Assert::same(0, $code);
	Assert::match("\e[%d%mTool\e[0m\n", $out);
	Assert::same(2, runRunner(new Command('tool'), ['--version'])[0]); // no version, no flag
});


test('a mistake of the command line is reported with the help of its command, by the exit code asked for', function () {
	$program = Command::from(#[Attributes\Command(name: 'tool')] fn(#[Attributes\Argument] string $file) => 0);
	[$code, $out, $err] = runRunner($program, ['--bogus', 'a']);
	Assert::same([2, ''], [$code, $out]);
	Assert::match("Error: Unknown option --bogus.\n\nUsage: tool [options] <file>\n%A%", stripColors($err));
	Assert::same(3, runRunner($program, [], usageExitCode: 3)[0]);

	$refusing = Command::from(#[Attributes\Command(name: 'tool')] fn(Command $command) => throw new UsageException('Refused.', $command));
	Assert::match("Error: Refused.\n\nUsage: tool [options]\n%A%", stripColors(runRunner($refusing, [])[2]));
});


test('an empty line prints the help where asked, before the required arguments are demanded', function () {
	$program = Command::from(#[Attributes\Command(name: 'tool')] fn(#[Attributes\Argument] string $file) => 1);
	[$code, $out] = runRunner($program, [], helpWhenEmpty: true, version: 'Tool 1.0');
	Assert::same(0, $code);
	Assert::match("Tool 1.0\n\nUsage: tool [options] <file>\n%A%", stripColors($out));
	Assert::same(1, runRunner($program, ['a'], helpWhenEmpty: true)[0]);
});


test('--no-color turns the colors of both consoles off, even for a mistake of the line, and is there only where asked', function () {
	$program = Command::from(#[Attributes\Command(name: 'tool')] fn() => 0);
	[$code, $out] = runRunner($program, ['--no-color', '--help'], colorFlag: true);
	Assert::same([0, stripColors($out)], [$code, $out]);
	[$code, , $err] = runRunner($program, ['--no-color', '--bogus'], colorFlag: true);
	Assert::same([2, stripColors($err)], [$code, $err]);
	Assert::same(2, runRunner(Command::from(#[Attributes\Command(name: 'tool')] fn() => 0), ['--no-color'])[0]);
	Assert::notSame(
		stripColors(runRunner($program, ['--', '--no-color'], colorFlag: true)[2]),
		runRunner($program, ['--', '--no-color'], colorFlag: true)[2],
	);
});


test('the flags of the runner are listed with the options of the program, before its commands and texts', function () {
	$program = new Command('tool');
	$program->addOption('--config');
	$program->addCommand('check', handler: fn() => 0);
	$program->addText('See the manual.');
	Assert::match(
		"Usage: tool [options] [command]\n\nOptions:\n  --config <value>\n  -h, --help        Print this help\n\nCommands:\n  check\n\nSee the manual.\n",
		stripColors(runRunner($program, ['--help'])[1]),
	);
});


test('a flag the program has of its own is answered as it is, an alias it took is left out', function () {
	$program = new Command('tool', handler: fn() => 0);
	$program->addFlag('--help', 'Show the manual', standalone: true);
	$program->addOption('--host', alias: '-h');
	[$code, $out] = runRunner($program, ['--help'], version: 'Tool 1.0');
	Assert::same(0, $code);
	Assert::match('%A%  --help %s%Show the manual%A%', stripColors($out));
	Assert::contains('-V, --version', stripColors($out));
});


test('an exception of the handler is reported by its message where asked, an Error always passes', function () {
	$failing = Command::from(#[Attributes\Command(name: 'tool')] fn() => throw new RuntimeException('Disk is `full`.'));
	[$code, $out, $err] = runRunner($failing, [], errorExitCode: 1);
	Assert::same([1, '', "Error: Disk is full.\n"], [$code, $out, stripColors($err)]);
	Assert::exception(fn() => runRunner($failing, []), RuntimeException::class, 'Disk is `full`.');
	$broken = Command::from(#[Attributes\Command(name: 'tool')] fn() => throw new Error('Bug.'));
	Assert::exception(fn() => runRunner($broken, [], errorExitCode: 1), Error::class, 'Bug.');
});


test('an alias a command below the program uses is left out too', function () {
	$program = new Command('tool');
	$program->addCommand('deploy', handler: fn() => 0)->addOption('--host', alias: '-h');
	[$code, $out] = runRunner($program, ['--help']);
	Assert::same(0, $code);
	Assert::match('%A%  --help %s%Print this help%A%', stripColors($out));
	Assert::notContains('-h, --help', stripColors($out));
});


test('only the program is run', function () {
	$program = new Command('tool');
	$check = $program->addCommand('check');
	Assert::exception(fn() => new Runner($check), InvalidArgumentException::class, "The runner runs the program, not command 'check'.");
});
