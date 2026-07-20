<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\ParseException;
use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


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
	Assert::exception(fn() => parseArgs($command, ['--output']), ParseException::class, 'Option --output requires a value.');
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
	Assert::exception(fn() => parseArgs($command, ['--format=csv']), ParseException::class, "Option --format: expects json or xml, 'csv' given.");

	$command = new Command;
	$command->addArgument('mode', enum: ['fast', 'safe']);
	Assert::exception(fn() => parseArgs($command, ['slow']), ParseException::class, "Argument <mode>: expects fast or safe, 'slow' given.");
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
	Assert::exception(fn() => parseArgs($command, []), ParseException::class, 'Missing required argument <file>.');
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
	Assert::exception(fn() => parseArgs($command, ['a', 'b']), ParseException::class, 'Unexpected argument b.');
	Assert::exception(fn() => parseArgs($command, ['a', 'b', 'c']), ParseException::class, 'Unexpected arguments b, c.');
});


test('an empty string is a value', function () {
	$command = new Command;
	$command->addArgument('input');
	$command->addOption('--output');
	Assert::same(['input' => '', '--output' => null], parseArgs($command, ['']));
	Assert::same(['--output' => '', 'input' => 'x'], parseArgs($command, ['--output', '', 'x']));
	Assert::same(['--output' => '', 'input' => 'x'], parseArgs($command, ['--output=', 'x']));
});


test('bundled short flags', function () {
	$command = new Command;
	$command->addFlag('--verbose', alias: '-v');
	$command->addFlag('--all', alias: '-a');
	$command->addOption('--output', alias: '-o');

	Assert::equal(['--verbose' => true, '--all' => true, '--output' => null], parseArgs($command, ['-va']));
	Assert::equal(['--verbose' => true, '--all' => true, '--output' => 'file.txt'], parseArgs($command, ['-vao', 'file.txt']));
	Assert::exception(fn() => parseArgs($command, ['-vx']), ParseException::class, 'Unknown option -vx.%A%');
	Assert::exception(fn() => parseArgs($command, ['-ov']), ParseException::class, 'Unknown option -ov.%A%');
});


test('a defined multi-letter short option is not a bundle', function () {
	$command = new Command;
	$command->addFlag('--verbose', alias: '-v');
	$command->addFlag('--verify', alias: '-vf');
	Assert::equal(['--verbose' => null, '--verify' => true], parseArgs($command, ['-vf']));
});


test('an unknown option suggests a close match', function () {
	$command = new Command;
	$command->addFlag('--verbose', alias: '-v');
	$command->addOption('--output');
	Assert::exception(fn() => parseArgs($command, ['--verbos']), ParseException::class, 'Unknown option --verbos. Did you mean --verbose?');
	Assert::exception(fn() => parseArgs($command, ['--zzzzzz']), ParseException::class, 'Unknown option --zzzzzz.');
});


test('control characters from the line are escaped in messages', function () {
	$command = new Command;
	$command->addArgument('input');
	Assert::exception(fn() => parseArgs($command, ["--x\e[31m"]), ParseException::class, 'Unknown option --x\033[31m.');
	Assert::exception(fn() => parseArgs($command, ['a', "b\rc"]), ParseException::class, 'Unexpected argument b\rc.');
});


test('a lone dash is a value', function () {
	$command = new Command;
	$command->addArgument('input', optional: true);
	$command->addOption('--output', alias: '-o');
	Assert::same(['input' => '-', '--output' => null], parseArgs($command, ['-']));
	Assert::same(['--output' => '-', 'input' => null], parseArgs($command, ['-o', '-']));
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
