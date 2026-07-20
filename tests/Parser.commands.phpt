<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\ParseException;
use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


function app(): Command
{
	$cli = new Command('dresscode');
	$cli->addFlag('--help', alias: '-h');
	$cli->addOption('--config', alias: '-c');
	$check = $cli->addCommand('check', 'Report violations');
	$check->addArgument('paths', optional: true, repeatable: true);
	$check->addFlag('--generate-baseline');
	$explain = $cli->addCommand('explain', 'What a rule is for');
	$explain->addArgument('rule');
	return $cli;
}


test('the first positional token picks the command', function () {
	$cli = app();
	$result = (new Parser)->parse($cli, ['check', 'src', 'tests']);
	Assert::same($cli->getCommand('check'), $result->command);
	Assert::equal(
		['--help' => null, '--config' => null, 'paths' => ['src', 'tests'], '--generate-baseline' => null],
		$result->toArray(),
	);

	$result = (new Parser)->parse($cli, []);
	Assert::same($cli, $result->command);
	Assert::equal(['--help' => null, '--config' => null], $result->toArray());
});


test('options of the commands above are valid on both sides of the command name', function () {
	$cli = app();
	Assert::same('x.neon', (new Parser)->parse($cli, ['-c', 'x.neon', 'check'])['--config']);
	Assert::same('x.neon', (new Parser)->parse($cli, ['check', '--config=x.neon'])['--config']);

	$cli->addFlag('--quiet'); // added after the commands, inherited all the same
	Assert::true((new Parser)->parse($cli, ['check', '--quiet'])['--quiet']);
});


test('an unknown command suggests a close one', function () {
	Assert::exception(
		fn() => (new Parser)->parse(app(), ['chekc']),
		ParseException::class,
		"Unknown command 'chekc'. Did you mean 'check'?",
	);
	Assert::exception(fn() => (new Parser)->parse(app(), ['zzz']), ParseException::class, "Unknown command 'zzz'.");
});


test('an option of another command says where it belongs', function () {
	Assert::exception(
		fn() => (new Parser)->parse(app(), ['explain', 'x', '--generate-baseline']),
		ParseException::class,
		"Option --generate-baseline belongs to command 'check'.",
	);
	Assert::exception(
		fn() => (new Parser)->parse(app(), ['--generate-baseline', 'check']),
		ParseException::class,
		"Option --generate-baseline belongs to command 'check'.",
	);
});


test('the exception knows the command in which the line went wrong', function () {
	$cli = app();
	$e = Assert::exception(fn() => (new Parser)->parse($cli, ['explain']), ParseException::class, 'Missing required argument <rule>.');
	Assert::same($cli->getCommand('explain'), $e->command);

	$e = Assert::exception(fn() => (new Parser)->parse($cli, ['explain', 'a', 'b']), ParseException::class, 'Unexpected argument b.');
	Assert::same($cli->getCommand('explain'), $e->command);

	$e = Assert::exception(fn() => (new Parser)->parse($cli, ['--nope']), ParseException::class, 'Unknown option --nope.');
	Assert::same($cli, $e->command);

	$cli->addOption('--mode', enum: ['a']);
	$e = Assert::exception(fn() => (new Parser)->parse($cli, ['explain', 'x', '--mode=b']), ParseException::class, "Option --mode: expects a, 'b' given.");
	Assert::same($cli->getCommand('explain'), $e->command);
});


test('a command given to the parser has to be the one the line runs', function () {
	$check = app()->getCommand('check');
	Assert::same($check, (new Parser)->parse($check, ['check', 'src'])->command);
	Assert::exception(
		fn() => (new Parser)->parse($check, ['explain', 'x']),
		ParseException::class,
		"The command line does not run command 'check'.",
	);
	Assert::exception(
		fn() => (new Parser)->parse($check, []),
		ParseException::class,
		"The command line does not run command 'check'.",
	);
});


test('commands nest', function () {
	$git = new Command('git');
	$git->addFlag('--verbose');
	$remote = $git->addCommand('remote');
	$add = $remote->addCommand('add');
	$add->addArgument('name');

	$result = (new Parser)->parse($remote, ['remote', '--verbose', 'add', 'origin']);
	Assert::same($add, $result->command);
	Assert::equal(['--verbose' => true, 'name' => 'origin'], $result->toArray());
});


test('after the separator a command name is only a value', function () {
	Assert::exception(fn() => (new Parser)->parse(app(), ['--', 'check']), ParseException::class, 'Unexpected argument check.');
});
