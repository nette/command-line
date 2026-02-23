<?php declare(strict_types=1);

use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('parseOnly with switch', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addArgument('input');

	Assert::same(['--help' => true], $cmd->parseOnly(['--help'], ['--help']));
	Assert::same(['--help' => null], $cmd->parseOnly(['--help'], []));
	Assert::same(['--help' => null], $cmd->parseOnly(['--help'], ['input.txt']));
});


test('parseOnly respects alias', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addArgument('input');

	Assert::same(['--help' => true], $cmd->parseOnly(['--help'], ['-h']));
});


test('parseOnly ignores unknown options', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addArgument('input');

	Assert::same(['--help' => true], $cmd->parseOnly(['--help'], ['--help', '--unknown']));
	Assert::same(['--help' => null], $cmd->parseOnly(['--help'], ['--unknown']));
});


test('parseOnly ignores positional arguments', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addArgument('input');

	Assert::same(['--help' => true], $cmd->parseOnly(['--help'], ['--help', 'input.txt']));
	Assert::same(['--help' => true], $cmd->parseOnly(['--help'], ['input.txt', '--help']));
});


test('parseOnly multiple options', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addSwitch('--version', '-V')
		->addArgument('input');

	Assert::same(
		['--help' => true, '--version' => null],
		$cmd->parseOnly(['--help', '--version'], ['--help']),
	);
	Assert::same(
		['--help' => null, '--version' => true],
		$cmd->parseOnly(['--help', '--version'], ['-V']),
	);
	Assert::same(
		['--help' => true, '--version' => true],
		$cmd->parseOnly(['--help', '--version'], ['--help', '--version']),
	);
});


test('parseOnly with option having value', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addOption('--config', '-c')
		->addArgument('input');

	Assert::same(['--config' => 'app.ini'], $cmd->parseOnly(['--config'], ['--config', 'app.ini']));
	Assert::same(['--config' => 'app.ini'], $cmd->parseOnly(['--config'], ['--config=app.ini']));
	Assert::same(['--config' => 'app.ini'], $cmd->parseOnly(['--config'], ['-c', 'app.ini']));
});


test('parseOnly does not throw on missing required argument', function () {
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addArgument('input');

	// This is the key feature - no exception thrown
	Assert::same(['--help' => true], $cmd->parseOnly(['--help'], ['--help']));
});


test('parseOnly uses default args from constructor', function () {
	$_SERVER['argv'] = ['script.php', '--help'];
	$cmd = (new Parser)
		->addSwitch('--help', '-h')
		->addArgument('input');

	Assert::same(['--help' => true], $cmd->parseOnly(['--help']));
});
