<?php declare(strict_types=1);

use Nette\CommandLine\{Command, ParseException, Parser};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


function command(): Command
{
	$command = new Command;
	$command->addFlag('--dry-run');
	$command->addOption('--output', default: 'out.txt');
	$command->addArgument('paths', optional: true, repeatable: true);
	return $command;
}


test('values are read by name', function () {
	$result = (new Parser)->parse(command(), ['--dry-run', 'src']);
	Assert::true($result->get('--dry-run'));
	Assert::same('out.txt', $result->get('--output'));
	Assert::same(['src'], $result->get('paths'));
	Assert::same($result->toArray(), iterator_to_array($result));
});


test('an unknown name is refused even with a fallback', function () {
	$result = (new Parser)->parse(command(), []);
	Assert::same('fallback', $result->get('--dry-run') ?? 'fallback'); // known, but null
	Assert::exception(fn() => $result->get('--nope') ?? 'fallback', InvalidArgumentException::class, "Unknown parameter '--nope'.");
});


test('isEmpty() tells that nothing was given', function () {
	Assert::true((new Parser)->parse(command(), [])->isEmpty());
	Assert::false((new Parser)->parse(command(), ['src'])->isEmpty());
});


test('wasProvided() tells a given value from the default', function () {
	$result = (new Parser)->parse(command(), ['--output=out.txt']);
	Assert::same('out.txt', $result->get('--output')); // equal to the default, yet given
	Assert::true($result->wasProvided('--output'));
	Assert::false($result->wasProvided('--dry-run'));
	Assert::false($result->wasProvided('paths'));
	Assert::exception(fn() => $result->wasProvided('--nope'), InvalidArgumentException::class, "Unknown parameter '--nope'.");
});


test('wasProvided() and foreach know a numeric name', function () {
	$command = new Command;
	$command->addFlag('-1');
	$command->addOption('-5');
	$command->addArgument('0');
	$command->addArgument('123', optional: true);
	$result = (new Parser)->parse($command, ['-1', '-5', 'x', 'a']);
	Assert::true($result->wasProvided('-1'));
	Assert::true($result->wasProvided('-5'));
	Assert::true($result->wasProvided('0'));
	Assert::false($result->wasProvided('123'));
	Assert::same('a', $result->get('0'));

	$names = [];
	foreach ($result as $name => $value) {
		$names[] = $name;
	}

	Assert::same(['-1', '-5', '0', '123'], $names);
});


test('a typo in a node with a numeric option is reported', function () {
	$command = new Command;
	$command->addFlag('-1');
	Assert::exception(
		fn() => (new Parser)->parse($command, ['--x']),
		ParseException::class,
		'Unknown option `--x`.',
	);
});
