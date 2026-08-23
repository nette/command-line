<?php declare(strict_types=1);

use Nette\CommandLine\{Command, ParseException, Parser};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


enum Format: string
{
	case Json = 'json';
	case Xml = 'xml';
}


enum Level: int
{
	case Low = 1;
	case High = 2;
}


test('flag', function () {
	$command = new Command;
	$command->addFlag('--verbose', alias: '-v');
	Assert::same(['--verbose' => null], parseArgs($command, []));
	Assert::same(['--verbose' => true], parseArgs($command, ['--verbose']));
	Assert::same(['--verbose' => true], parseArgs($command, ['-v']));
});


test('option', function () {
	$command = new Command;
	$command->addOption('--output', alias: '-o');
	Assert::same(['--output' => null], parseArgs($command, []));
	Assert::same(['--output' => 'file.txt'], parseArgs($command, ['--output', 'file.txt']));
	Assert::same(['--output' => 'file.txt'], parseArgs($command, ['--output=file.txt']));
	Assert::same(['--output' => 'file.txt'], parseArgs($command, ['-o', 'file.txt']));
	Assert::exception(fn() => parseArgs($command, ['--output']), ParseException::class, 'Option `--output` requires a value.');
});


test('option with an optional value', function () {
	$command = new Command;
	$command->addOption('--format', valueOptional: true, default: 'json');
	$command->addArgument('input', optional: true);
	Assert::same(['--format' => 'json', 'input' => null], parseArgs($command, []));
	Assert::same(['--format' => 'xml', 'input' => null], parseArgs($command, ['--format=xml']));
	Assert::same(['--format' => true, 'input' => null], parseArgs($command, ['--format']));
	Assert::same(['--format' => true, 'input' => 'file.txt'], parseArgs($command, ['--format', 'file.txt']));
});


test('enum', function () {
	$command = new Command;
	$command->addOption('--format', enum: ['json', 'xml']);
	Assert::same(['--format' => 'xml'], parseArgs($command, ['--format=xml']));
	Assert::exception(
		fn() => parseArgs($command, ['--format=csv']),
		ParseException::class,
		'Option `--format`: expects `json` or `xml`, `csv` given.',
	);

	$command = new Command;
	$command->addArgument('mode', enum: ['fast', 'safe']);
	Assert::exception(
		fn() => parseArgs($command, ['slow']),
		ParseException::class,
		'Argument `<mode>`: expects `fast` or `safe`, `slow` given.',
	);
});


test('a backed enum parses as its case', function () {
	$command = new Command;
	$command->addOption('--format', enum: Format::class, default: Format::Json);
	$command->addOption('--level', enum: Level::class, repeatable: true);
	Assert::same(['--format' => Format::Json, '--level' => []], parseArgs($command, []));
	Assert::same(
		['--format' => Format::Xml, '--level' => [Level::High, Level::Low]],
		parseArgs($command, ['--format=xml', '--level=2', '--level=1']),
	);
	Assert::exception(
		fn() => parseArgs($command, ['--level=3']),
		ParseException::class,
		'Option `--level`: expects `1` or `2`, `3` given.',
	);
});


test('repeatable option', function () {
	$command = new Command;
	$command->addOption('--include', alias: '-I', repeatable: true);
	Assert::same(['--include' => []], parseArgs($command, []));
	Assert::same(['--include' => ['a', 'b']], parseArgs($command, ['-I', 'a', '-I', 'b']));
});


test('normalizer', function () {
	$command = new Command;
	$command->addOption('--count', normalizer: fn($v) => (int) $v);
	Assert::same(['--count' => 42], parseArgs($command, ['--count=42']));
});


test('argument', function () {
	$command = new Command;
	$command->addArgument('file');
	Assert::same(['file' => 'test.txt'], parseArgs($command, ['test.txt']));
	Assert::exception(fn() => parseArgs($command, []), ParseException::class, 'Missing required argument `<file>`.');
});


test('optional and repeatable arguments', function () {
	$command = new Command;
	$command->addArgument('output', optional: true, default: 'out.txt');
	Assert::same(['output' => 'out.txt'], parseArgs($command, []));
	Assert::same(['output' => 'custom.txt'], parseArgs($command, ['custom.txt']));

	$command = new Command;
	$command->addArgument('files', optional: true, repeatable: true);
	Assert::same(['files' => []], parseArgs($command, []));
	Assert::same(['files' => ['a.txt', 'b.txt']], parseArgs($command, ['a.txt', 'b.txt']));
});


test('every unexpected argument is named', function () {
	$command = new Command;
	$command->addArgument('input');
	Assert::exception(fn() => parseArgs($command, ['a', 'b']), ParseException::class, 'Unexpected argument `b`.');
	Assert::exception(fn() => parseArgs($command, ['a', 'b', 'c']), ParseException::class, 'Unexpected arguments `b`, `c`.');
	Assert::exception(fn() => parseArgs($command, ['a', '']), ParseException::class, "Unexpected argument `''`.");
	Assert::exception(fn() => parseArgs($command, ['a', 'b c']), ParseException::class, 'Unexpected argument `b c`.');
});


test('an empty string is a value', function () {
	$command = new Command;
	$command->addArgument('input');
	$command->addOption('--output');
	Assert::same(['input' => '', '--output' => null], parseArgs($command, ['']));
	Assert::same(['--output' => '', 'input' => 'x'], parseArgs($command, ['--output', '', 'x']));
	Assert::same(['--output' => '', 'input' => 'x'], parseArgs($command, ['--output=', 'x']));
});


test('control characters from the line are escaped in messages', function () {
	$command = new Command;
	$command->addArgument('input');
	Assert::exception(fn() => parseArgs($command, ["--x\e[31m"]), ParseException::class, 'Unknown option `--x\033[31m`.');
	Assert::exception(fn() => parseArgs($command, ['a', "b\rc"]), ParseException::class, 'Unexpected argument `b\\rc`.');
});


test('a lone dash is a value', function () {
	$command = new Command;
	$command->addArgument('input', optional: true);
	$command->addOption('--output', alias: '-o');
	Assert::same(['input' => '-', '--output' => null], parseArgs($command, ['-']));
	Assert::same(['--output' => '-', 'input' => null], parseArgs($command, ['-o', '-']));
});


test('a negatable flag', function () {
	$command = new Command;
	$command->addFlag('--color', alias: '-c', negatable: true);
	Assert::same(['--color' => null], parseArgs($command, []));
	Assert::same(['--color' => true], parseArgs($command, ['-c']));
	Assert::same(['--color' => false], parseArgs($command, ['--no-color']));
	Assert::same(['--color' => false], parseArgs($command, ['--color', '--no-color']));
	Assert::exception(fn() => parseArgs($command, ['--no-color=1']), ParseException::class, 'Option `--no-color` does not accept a value.');
});


test('-- makes the rest positional', function () {
	$command = new Command;
	$command->addFlag('--verbose', alias: '-v');
	$command->addArgument('files', optional: true, repeatable: true);
	Assert::equal(['--verbose' => null, 'files' => ['-x']], parseArgs($command, ['--', '-x']));
	Assert::equal(['--verbose' => true, 'files' => ['--verbose', '--']], parseArgs($command, ['-v', '--', '--verbose', '--']));
});


test('without arguments the command line of the process is read', function () {
	$_SERVER['argv'] = ['script.php', '--verbose'];
	$command = new Command;
	$command->addFlag('--verbose');
	Assert::same(['--verbose' => true], (new Parser)->parse($command)->toArray());
});
