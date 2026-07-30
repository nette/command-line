<?php declare(strict_types=1);

// A standalone flag answers on its own: --help has to work before the configuration is read,
// the paths are resolved and the locks are taken, so nothing else may be validated or converted.
// The other flags need no conversion, so they are taken as given, and --help --no-color can draw the help plain.

use Nette\CommandLine\{Command, ParseException, Parser};
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


test('the other flags are taken, the options with a value and the arguments are not', function () {
	$command = cli();
	$command->addFlag('--color', negatable: true, default: true);
	$command->addFlag('--verbose', alias: '-v', negatable: true, repeatable: true);
	$result = (new Parser)->parse($command, ['-v', '--no-color', '--help', '--no-verbose', '-v', '--jobs=3', 'in']);
	Assert::false($result['--color']);
	Assert::same([true, false, true], $result['--verbose']);
	Assert::same('8', $result['--jobs']);
	Assert::true($result->wasProvided('--jobs')); // given, though not taken
	Assert::null($result['input']);
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


test('a negated standalone flag does not answer', function () {
	$command = new Command;
	$command->addFlag('--help', standalone: true, negatable: true);
	$command->addArgument('input');
	Assert::exception(fn() => parseArgs($command, ['--no-help']), ParseException::class, 'Missing required argument <input>.');
	Assert::same(['--help' => false, 'input' => 'x'], parseArgs($command, ['--no-help', 'x']));

	$command->addFlag('--version', standalone: true);
	Assert::false(parseArgs($command, ['--version', '--no-help'])['--help']); // taken while another one answers
});
