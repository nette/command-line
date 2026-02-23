<?php declare(strict_types=1);

use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('addSwitch basic', function () {
	$cmd = (new Parser)->addSwitch('--verbose');
	Assert::same(['--verbose' => null], $cmd->parse([]));
	Assert::same(['--verbose' => true], $cmd->parse(['--verbose']));
});


test('addSwitch with alias', function () {
	$cmd = (new Parser)->addSwitch('--verbose', '-v');
	Assert::same(['--verbose' => null], $cmd->parse([]));
	Assert::same(['--verbose' => true], $cmd->parse(['-v']));
	Assert::same(['--verbose' => true], $cmd->parse(['--verbose']));
});


test('addOption basic', function () {
	$cmd = (new Parser)->addOption('--output');
	Assert::same(['--output' => null], $cmd->parse([]));
	Assert::same(['--output' => 'file.txt'], $cmd->parse(['--output', 'file.txt']));
	Assert::same(['--output' => 'file.txt'], $cmd->parse(['--output=file.txt']));

	Assert::exception(
		fn() => $cmd->parse(['--output']),
		Throwable::class,
		'Option --output requires argument.',
	);
});


test('addOption with alias', function () {
	$cmd = (new Parser)->addOption('--output', '-o');
	Assert::same(['--output' => 'file.txt'], $cmd->parse(['-o', 'file.txt']));
	Assert::same(['--output' => 'file.txt'], $cmd->parse(['--output=file.txt']));
});


test('addOption with optionalValue and fallback', function () {
	$cmd = (new Parser)->addOption('--format', optionalValue: true, fallback: 'json');
	Assert::same(['--format' => 'json'], $cmd->parse([]));
	Assert::same(['--format' => 'xml'], $cmd->parse(['--format=xml']));
	Assert::same(['--format' => true], $cmd->parse(['--format']));
});


test('addOption with optionalValue', function () {
	$cmd = (new Parser)->addOption('--format', optionalValue: true);
	Assert::same(['--format' => null], $cmd->parse([]));
	Assert::same(['--format' => 'xml'], $cmd->parse(['--format=xml']));
	Assert::same(['--format' => true], $cmd->parse(['--format']));
});


test('addOption with enum', function () {
	$cmd = (new Parser)->addOption('--format', optionalValue: true, fallback: 'json', enum: ['json', 'xml']);
	Assert::same(['--format' => 'json'], $cmd->parse([]));
	Assert::same(['--format' => 'xml'], $cmd->parse(['--format=xml']));

	Assert::exception(
		fn() => $cmd->parse(['--format=csv']),
		Throwable::class,
		'Value of option --format must be json, or xml.',
	);
});


test('addOption repeatable', function () {
	$cmd = (new Parser)->addOption('--include', '-I', optionalValue: true, repeatable: true);
	Assert::same(['--include' => []], $cmd->parse([]));
	Assert::same(['--include' => ['a', 'b']], $cmd->parse(['-I', 'a', '-I', 'b']));
});


test('addOption with normalizer', function () {
	$cmd = (new Parser)->addOption('--count', optionalValue: true, fallback: '0', normalizer: fn($v) => (int) $v);
	Assert::same(['--count' => 42], $cmd->parse(['--count=42']));
});


test('addArgument basic', function () {
	$cmd = (new Parser)->addArgument('file');
	Assert::same(['file' => 'test.txt'], $cmd->parse(['test.txt']));

	Assert::exception(
		fn() => $cmd->parse([]),
		Throwable::class,
		'Missing required argument <file>.',
	);
});


test('addArgument optional with default', function () {
	$cmd = (new Parser)->addArgument('output', optional: true, fallback: 'out.txt');
	Assert::same(['output' => 'out.txt'], $cmd->parse([]));
	Assert::same(['output' => 'custom.txt'], $cmd->parse(['custom.txt']));
});


test('addArgument optional with null', function () {
	$cmd = (new Parser)->addArgument('output', optional: true);
	Assert::same(['output' => null], $cmd->parse([]));
	Assert::same(['output' => 'custom.txt'], $cmd->parse(['custom.txt']));
});


test('addArgument repeatable', function () {
	$cmd = (new Parser)->addArgument('files', optional: true, repeatable: true);
	Assert::same(['files' => []], $cmd->parse([]));
	Assert::same(['files' => ['a.txt', 'b.txt']], $cmd->parse(['a.txt', 'b.txt']));
});


test('addArgument with normalizer', function () {
	$cmd = (new Parser)->addArgument('count', normalizer: fn($v) => (int) $v);
	Assert::same(['count' => 42], $cmd->parse(['42']));
});


test('fluent chain', function () {
	$cmd = (new Parser)
		->addSwitch('--verbose', '-v')
		->addOption('--output', '-o', optionalValue: true, fallback: 'out.txt')
		->addArgument('input');

	Assert::equal([
		'--verbose' => true,
		'--output' => 'out.txt',
		'input' => 'data.txt',
	], $cmd->parse(['-v', 'data.txt']));

	Assert::equal([
		'--verbose' => null,
		'--output' => 'custom.txt',
		'input' => 'data.txt',
	], $cmd->parse(['-o', 'custom.txt', 'data.txt']));
});


test('mixed fluent and addFromHelp', function () {
	$cmd = (new Parser)
		->addSwitch('--verbose')
		->addFromHelp('
			--quiet  Quiet mode
		');

	Assert::equal(['--verbose' => true, '--quiet' => null], $cmd->parse(['--verbose']));
	Assert::equal(['--verbose' => null, '--quiet' => true], $cmd->parse(['--quiet']));
});
