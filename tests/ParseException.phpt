<?php declare(strict_types=1);

use Nette\CommandLine\{Command, ParseException, ParseFailure};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('the exception tells what was wrong and with which parameter', function () {
	$command = new Command;
	$output = $command->addOption('--output');
	$verbose = $command->addFlag('--verbose');
	$input = $command->addArgument('input', enum: ['a']);
	$command->addOption('--level', alias: '-l');
	$cases = [
		[['--nope=1', 'a'], ParseFailure::UnknownOption, null, '--nope'],
		[['a', '--output'], ParseFailure::MissingValue, $output, '--output'],
		[['a', '-l'], ParseFailure::MissingValue, $command->getParameter('--level'), '-l'],
		[['a', '--verbose=1'], ParseFailure::UnexpectedValue, $verbose, '--verbose'],
		[['a', 'b', 'c'], ParseFailure::UnexpectedArgument, null, 'b'],
		[[], ParseFailure::MissingArgument, $input, null],
		[['x'], ParseFailure::InvalidValue, $input, 'x'],
	];

	foreach ($cases as [$args, $reason, $parameter, $token]) {
		$e = Assert::exception(fn() => parseArgs($command, $args), ParseException::class);
		Assert::same($reason, $e->reason, implode(' ', $args));
		Assert::same($parameter, $e->parameter, implode(' ', $args));
		Assert::same($token, $e->token, implode(' ', $args));
	}
});


test('an exception from a normalizer is an invalid value of its parameter', function () {
	$command = new Command;
	$count = $command->addOption('--count', normalizer: fn() => throw new Exception('not a number'));
	$e = Assert::exception(fn() => parseArgs($command, ['--count=x']), ParseException::class, 'Option --count: not a number');
	Assert::same(ParseFailure::InvalidValue, $e->reason);
	Assert::same($count, $e->parameter);
	Assert::same('x', $e->token);

	$tags = new Command;
	$tags->addOption('--tag', repeatable: true, normalizer: fn($v) => $v === 'q' ? throw new Exception('bad') : $v);
	Assert::same('q', Assert::exception(fn() => parseArgs($tags, ['--tag=x', '--tag=q']), ParseException::class)->token);

	$own = new Command; // a normalizer throwing a ParseException of its own passes it through, with no token
	$own->addOption('--x', normalizer: fn() => throw new ParseException('custom', $own));
	Assert::null(Assert::exception(fn() => parseArgs($own, ['--x=1']), ParseException::class, 'custom')->token);
});
