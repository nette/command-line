<?php declare(strict_types=1);

// A standalone flag answers on its own: --help has to work before the configuration is read,
// the paths are resolved and the locks are taken, so nothing else may be validated or converted.

use Nette\CommandLine\Command;
use Nette\CommandLine\ParseException;
use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


function cli(): Command
{
	$command = new Command;
	$command->addFlag('--help', alias: '-h', standalone: true);
	$command->addFlag('--version', standalone: true);
	$command->addOption('--jobs', normalizer: fn() => throw new Exception('never runs'), default: '8');
	$command->addOption('--tag', repeatable: true);
	$command->addArgument('input');
	return $command;
}


test('it answers even though a required argument is missing', function () {
	Assert::same(
		['--help' => true, '--version' => null, '--jobs' => '8', '--tag' => [], 'input' => null],
		parseArgs(cli(), ['--help']),
	);
	Assert::true(parseArgs(cli(), ['-h'])['--help']);
});


test('nothing else is converted or checked', function () {
	$args = parseArgs(cli(), ['--help', '--jobs', 'nonsense', '--tag', 'a']);
	Assert::same('8', $args['--jobs']); // the normalizer would have thrown
	Assert::same([], $args['--tag']);
});


test('two of them are both true', function () {
	$args = parseArgs(cli(), ['--help', '--version']);
	Assert::true($args['--help']);
	Assert::true($args['--version']);
});


test('a repeatable one is a list, as when it is not standalone', function () {
	$command = new Command;
	$command->addFlag('--help', standalone: true, repeatable: true);
	$command->addArgument('input');
	Assert::same(['--help' => [true, true], 'input' => null], parseArgs($command, ['--help', '--help']));
});


test('after the separator it is a value, not an option', function () {
	Assert::same(
		['input' => '--help', '--help' => null, '--version' => null, '--jobs' => '8', '--tag' => []],
		parseArgs(cli(), ['--', '--help']),
	);
});


test('the line still has to be a line', function () {
	Assert::exception(fn() => parseArgs(cli(), ['--help', '--unknown']), ParseException::class, 'Unknown option --unknown.');
	Assert::exception(fn() => parseArgs(cli(), ['--help=1']), ParseException::class, 'Option --help does not accept a value.');
});


test('it answers for the command the line runs', function () {
	$cli = new Command('tool');
	$cli->addFlag('--help', standalone: true);
	$check = $cli->addCommand('check');
	$check->addArgument('paths');

	foreach ([['check', '--help'], ['--help', 'check']] as $args) {
		$result = (new Parser)->parse($cli, $args);
		Assert::same($check, $result->command);
		Assert::equal(['--help' => true, 'paths' => null], $result->toArray());
	}
});
