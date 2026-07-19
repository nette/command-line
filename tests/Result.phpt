<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\Parser;
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


test('values are read like an array', function () {
	$result = (new Parser)->parse(command(), ['--dry-run', 'src']);
	Assert::true($result['--dry-run']);
	Assert::same('out.txt', $result['--output']);
	Assert::same(['src'], $result['paths']);
	Assert::same($result->toArray(), iterator_to_array($result));
});


test('isset() answers like on an array', function () {
	$result = (new Parser)->parse(command(), ['src']);
	Assert::false(isset($result['--dry-run'])); // known, but null
	Assert::true(isset($result['--output']));
	Assert::false(isset($result['--nope']));
});


test('an unknown name is refused and the result cannot be changed', function () {
	$result = (new Parser)->parse(command(), []);
	Assert::exception(fn() => $result['--nope'], InvalidArgumentException::class, "Unknown parameter '--nope'.");
	Assert::exception(function () use ($result) {
		$result['--dry-run'] = true;
	}, LogicException::class, 'The result is read-only.');
	Assert::exception(function () use ($result) {
		unset($result['--dry-run']);
	}, LogicException::class, 'The result is read-only.');
});


test('isEmpty() tells that nothing was given', function () {
	Assert::true((new Parser)->parse(command(), [])->isEmpty());
	Assert::false((new Parser)->parse(command(), ['src'])->isEmpty());
});


test('wasProvided() tells a given value from the default', function () {
	$result = (new Parser)->parse(command(), ['--output=out.txt']);
	Assert::same('out.txt', $result['--output']); // equal to the default, yet given
	Assert::true($result->wasProvided('--output'));
	Assert::false($result->wasProvided('--dry-run'));
	Assert::false($result->wasProvided('paths'));
	Assert::exception(fn() => $result->wasProvided('--nope'), InvalidArgumentException::class, "Unknown parameter '--nope'.");
});
