<?php

declare(strict_types=1);

use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';



test('', function () {
	$cmd = new Parser('
		-p
		--p
		--a-b
	');

	Assert::same(['-p' => null, '--p' => null, '--a-b' => null], $cmd->parse([]));
	Assert::same(['-p' => true, '--p' => null, '--a-b' => null], $cmd->parse(['-p']));

	$cmd = new Parser('
		-p  description
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
});


test('default value', function () {
	$cmd = new Parser('
		-p  (default: 123)
	');

	Assert::same(['-p' => '123'], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));


	$cmd = new Parser('
		-p
	', [
		'-p' => [Parser::Default => 123],
	]);

	Assert::same(['-p' => 123], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
});


test('alias', function () {
	$cmd = new Parser('
		-p | --param
	');

	Assert::same(['--param' => null], $cmd->parse([]));
	Assert::same(['--param' => true], $cmd->parse(['-p']));
	Assert::same(['--param' => true], $cmd->parse(['--param']));
	Assert::same(['--param' => true], $cmd->parse(explode(' ', '-p --param')));
	Assert::exception(
		fn() => $cmd->parse(['-p=val']),
		Throwable::class,
		'Option --param has not argument.',
	);

	$cmd = new Parser('
		-p --param
	');

	Assert::same(['--param' => true], $cmd->parse(['-p']));

	$cmd = new Parser('
		-p, --param
	');

	Assert::same(['--param' => true], $cmd->parse(['-p']));
});


test('argument', function () {
	$cmd = new Parser('
		-p param
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p=val')));
	Assert::same(['-p' => 'val2'], $cmd->parse(explode(' ', '-p val1 -p val2')));

	Assert::exception(
		fn() => $cmd->parse(['-p']),
		Throwable::class,
		'Option -p requires argument.',
	);

	Assert::exception(
		fn() => $cmd->parse(['-p', '-a']),
		Throwable::class,
		'Option -p requires argument.',
	);


	$cmd = new Parser('
		-p=<param>
	');

	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));
});



test('optional argument', function () {
	$cmd = new Parser('
		-p [param]
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));


	$cmd = new Parser('
		-p param
	', [
		'-p' => [Parser::Default => 123],
	]);

	Assert::same(['-p' => 123], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));


	$cmd = new Parser('
		-p param
	', [
		'-p' => [Parser::Optional => true],
	]);

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));
});



test('repeatable argument', function () {
	$cmd = new Parser('
		-p [param]...
	');

	Assert::same(['-p' => []], $cmd->parse([]));
	Assert::same(['-p' => [true]], $cmd->parse(['-p']));
	Assert::same(['-p' => ['val']], $cmd->parse(explode(' ', '-p val')));
	Assert::same(['-p' => ['val1', 'val2']], $cmd->parse(explode(' ', '-p val1 -p val2')));
});



test('enumerates', function () {
	$cmd = new Parser('
		-p <a|b|c>
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::exception(
		fn() => $cmd->parse(['-p']),
		Throwable::class,
		'Option -p requires argument.',
	);
	Assert::same(['-p' => 'a'], $cmd->parse(explode(' ', '-p a')));
	Assert::exception(
		fn() => $cmd->parse(explode(' ', '-p foo')),
		Throwable::class,
		'Value of option -p must be a, or b, or c.',
	);


	$cmd = new Parser('
		-p [a|b|c]
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'a'], $cmd->parse(explode(' ', '-p a')));
	Assert::exception(
		fn() => $cmd->parse(explode(' ', '-p foo')),
		Throwable::class,
		'Value of option -p must be a, or b, or c.',
	);
});



test('realpath', function () {
	$cmd = new Parser('
		-p <path>
	', [
		'-p' => [Parser::RealPath => true],
	]);

	Assert::exception(
		fn() => $cmd->parse(['-p', 'xyz']),
		Throwable::class,
		"File path 'xyz' not found.",
	);
	Assert::same(['-p' => __FILE__], $cmd->parse(['-p', __FILE__]));
});



test('normalizer', function () {
	$cmd = new Parser('
		-p param
	', [
		'-p' => [Parser::Normalizer => fn($arg) => "$arg-normalized"],
	]);

	Assert::same(['-p' => 'val-normalized'], $cmd->parse(explode(' ', '-p val')));


	$cmd = new Parser('
		-p <a|b>
	', [
		'-p' => [Parser::Normalizer => fn() => 'a'],
	]);

	Assert::same(['-p' => 'a'], $cmd->parse(explode(' ', '-p xxx')));


	$cmd = new Parser('
		-p <a|b>
	', [
		'-p' => [Parser::Normalizer => fn() => ['a', 'foo']],
	]);

	Assert::same(['-p' => ['a', 'foo']], $cmd->parse(explode(' ', '-p xxx')));
});



test('positional arguments', function () {
	$cmd = new Parser('', [
		'pos' => [],
	]);

	Assert::same(['pos' => 'val'], $cmd->parse(['val']));

	Assert::exception(
		fn() => $cmd->parse([]),
		Throwable::class,
		'Missing required argument <pos>.',
	);

	Assert::exception(
		fn() => $cmd->parse(['val1', 'val2']),
		Throwable::class,
		'Unexpected parameter val2.',
	);

	$cmd = new Parser('', [
		'pos' => [Parser::Repeatable => true],
	]);

	Assert::same(['pos' => ['val1', 'val2']], $cmd->parse(['val1', 'val2']));


	$cmd = new Parser('', [
		'pos' => [Parser::Optional => true],
	]);

	Assert::same(['pos' => null], $cmd->parse([]));


	$cmd = new Parser('', [
		'pos' => [Parser::Default => 'default', Parser::Repeatable => true],
	]);
	Assert::same(['pos' => ['default']], $cmd->parse([]));
});



test('errors', function () {
	$cmd = new Parser('
		-p
	');

	Assert::exception(
		fn() => $cmd->parse(['-x']),
		Throwable::class,
		'Unknown option -x.',
	);

	Assert::exception(
		fn() => $cmd->parse(['val']),
		Throwable::class,
		'Unexpected parameter val.',
	);
});
