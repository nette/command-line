<?php

declare(strict_types=1);

use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';



test(function () {
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


test(function () { // default value
	$cmd = new Parser('
		-p  (default: 123)
	');

	Assert::same(['-p' => '123'], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));


	$cmd = new Parser('
		-p
	', [
		'-p' => [Parser::VALUE => 123],
	]);

	Assert::same(['-p' => 123], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
});


test(function () { // alias
	$cmd = new Parser('
		-p | --param
	');

	Assert::same(['--param' => null], $cmd->parse([]));
	Assert::same(['--param' => true], $cmd->parse(['-p']));
	Assert::same(['--param' => true], $cmd->parse(['--param']));
	Assert::same(['--param' => true], $cmd->parse(explode(' ', '-p --param')));
	Assert::exception(function () use ($cmd) {
		$cmd->parse(['-p=val']);
	}, Exception::class, 'Option --param has not argument.');

	$cmd = new Parser('
		-p --param
	');

	Assert::same(['--param' => true], $cmd->parse(['-p']));

	$cmd = new Parser('
		-p, --param
	');

	Assert::same(['--param' => true], $cmd->parse(['-p']));
});


test(function () { // argument
	$cmd = new Parser('
		-p param
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p=val')));
	Assert::same(['-p' => 'val2'], $cmd->parse(explode(' ', '-p val1 -p val2')));

	Assert::exception(function () use ($cmd) {
		$cmd->parse(['-p']);
	}, Exception::class, 'Option -p requires argument.');

	Assert::exception(function () use ($cmd) {
		$cmd->parse(['-p', '-a']);
	}, Exception::class, 'Option -p requires argument.');


	$cmd = new Parser('
		-p=<param>
	');

	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));
});



test(function () { // optional argument
	$cmd = new Parser('
		-p [param]
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));


	$cmd = new Parser('
		-p param
	', [
		'-p' => [Parser::VALUE => 123],
	]);

	Assert::same(['-p' => 123], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));


	$cmd = new Parser('
		-p param
	', [
		'-p' => [Parser::OPTIONAL => true],
	]);

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'val'], $cmd->parse(explode(' ', '-p val')));
});



test(function () { // repeatable argument
	$cmd = new Parser('
		-p [param]...
	');

	Assert::same(['-p' => []], $cmd->parse([]));
	Assert::same(['-p' => [true]], $cmd->parse(['-p']));
	Assert::same(['-p' => ['val']], $cmd->parse(explode(' ', '-p val')));
	Assert::same(['-p' => ['val1', 'val2']], $cmd->parse(explode(' ', '-p val1 -p val2')));
});



test(function () { // enumerates
	$cmd = new Parser('
		-p <a|b|c>
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::exception(function () use ($cmd) {
		$cmd->parse(['-p']);
	}, Exception::class, 'Option -p requires argument.');
	Assert::same(['-p' => 'a'], $cmd->parse(explode(' ', '-p a')));
	Assert::exception(function () use ($cmd) {
		$cmd->parse(explode(' ', '-p foo'));
	}, Exception::class, 'Value of option -p must be a, or b, or c.');


	$cmd = new Parser('
		-p [a|b|c]
	');

	Assert::same(['-p' => null], $cmd->parse([]));
	Assert::same(['-p' => true], $cmd->parse(['-p']));
	Assert::same(['-p' => 'a'], $cmd->parse(explode(' ', '-p a')));
	Assert::exception(function () use ($cmd) {
		$cmd->parse(explode(' ', '-p foo'));
	}, Exception::class, 'Value of option -p must be a, or b, or c.');
});



test(function () { // realpath
	$cmd = new Parser('
		-p <path>
	', [
		'-p' => [Parser::REALPATH => true],
	]);

	Assert::exception(function () use ($cmd) {
		$cmd->parse(['-p', 'xyz']);
	}, Exception::class, "File path 'xyz' not found.");
	Assert::same(['-p' => __FILE__], $cmd->parse(['-p', __FILE__]));
});



test(function () { // positional arguments
	$cmd = new Parser('', [
		'pos' => [],
	]);

	Assert::same(['pos' => 'val'], $cmd->parse(['val']));

	Assert::exception(function () use ($cmd) {
		$cmd->parse([]);
	}, Exception::class, 'Missing required argument <pos>.');

	Assert::exception(function () use ($cmd) {
		$cmd->parse(['val1', 'val2']);
	}, Exception::class, 'Unexpected parameter val2.');

	$cmd = new Parser('', [
		'pos' => [Parser::REPEATABLE => true],
	]);

	Assert::same(['pos' => ['val1', 'val2']], $cmd->parse(['val1', 'val2']));


	$cmd = new Parser('', [
		'pos' => [Parser::OPTIONAL => true],
	]);

	Assert::same(['pos' => null], $cmd->parse([]));


	$cmd = new Parser('', [
		'pos' => [Parser::VALUE => 'default', Parser::REPEATABLE => true],
	]);

	Assert::same(['pos' => ['default']], $cmd->parse([]));
});



test(function () { // errors
	$cmd = new Parser('
		-p
	');

	Assert::exception(function () use ($cmd) {
		$cmd->parse(['-x']);
	}, Exception::class, 'Unknown option -x.');

	Assert::exception(function () use ($cmd) {
		$cmd->parse(['val']);
	}, Exception::class, 'Unexpected parameter val.');
});
