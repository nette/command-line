<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\ParseError;
use Nette\CommandLine\ParseException;
use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('the exception tells what was wrong and with which parameter', function () {
	$command = new Command;
	$output = $command->addOption('--output');
	$verbose = $command->addFlag('--verbose');
	$input = $command->addArgument('input', enum: ['a']);
	$cases = [
		[['--nope', 'a'], ParseError::UnknownOption, null],
		[['a', '--output'], ParseError::MissingValue, $output],
		[['a', '--verbose=1'], ParseError::UnexpectedValue, $verbose],
		[['a', 'b'], ParseError::UnexpectedArgument, null],
		[[], ParseError::MissingArgument, $input],
		[['x'], ParseError::InvalidValue, $input],
	];

	foreach ($cases as [$args, $reason, $parameter]) {
		$e = Assert::exception(fn() => parseArgs($command, $args), ParseException::class);
		Assert::same($reason, $e->reason, implode(' ', $args));
		Assert::same($parameter, $e->parameter, implode(' ', $args));
	}
});


test('an exception from a normalizer is an invalid value of its parameter', function () {
	$command = new Command;
	$count = $command->addOption('--count', normalizer: fn() => throw new Exception('not a number'));
	$e = Assert::exception(fn() => parseArgs($command, ['--count=x']), ParseException::class, 'Option --count: not a number');
	Assert::same(ParseError::InvalidValue, $e->reason);
	Assert::same($count, $e->parameter);
});


test('commands', function () {
	$cli = new Command('tool');
	$check = $cli->addCommand('check');
	$cli->addCommand('fix');

	$e = Assert::exception(fn() => (new Parser)->parse($cli, ['zzz']), ParseException::class);
	Assert::same(ParseError::UnknownCommand, $e->reason);
	Assert::null($e->parameter);

	$e = Assert::exception(fn() => (new Parser)->parse($check, ['fix']), ParseException::class);
	Assert::same(ParseError::CommandMismatch, $e->reason);

	$required = new Command('tool', commandRequired: true);
	$required->addCommand('check');
	$e = Assert::exception(fn() => (new Parser)->parse($required, []), ParseException::class, 'Missing command.');
	Assert::same(ParseError::MissingCommand, $e->reason);
});
