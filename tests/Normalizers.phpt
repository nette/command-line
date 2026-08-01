<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\Normalizers;
use Nette\CommandLine\ParseException;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('int() accepts an integer and refuses anything else', function () {
	$command = new Command;
	$command->addOption('--count', normalizer: Normalizers::int());
	Assert::same(42, parseArgs($command, ['--count=42'])['--count']);
	Assert::same(-7, parseArgs($command, ['--count', '-7'])['--count']);
	Assert::same(8, parseArgs($command, ['--count=008'])['--count']);
	Assert::exception(fn() => parseArgs($command, ['--count=1.8']), ParseException::class, "Option --count: expects an integer, '1.8' given.");
	Assert::exception(fn() => parseArgs($command, ['--count=abc']), ParseException::class, "Option --count: expects an integer, 'abc' given.");
	Assert::exception(
		fn() => parseArgs($command, ['--count=99999999999999999999']),
		ParseException::class,
		"Option --count: expects an integer, '99999999999999999999' given.",
	);
});


test('float() accepts a decimal number and refuses anything else', function () {
	$command = new Command;
	$command->addOption('--x', normalizer: Normalizers::float());
	Assert::same(1.5, parseArgs($command, ['--x=1.5'])['--x']);
	Assert::same(-2000.0, parseArgs($command, ['--x', '-2e3'])['--x']);
	Assert::same(3.0, parseArgs($command, ['--x=3'])['--x']);
	Assert::same(0.25, parseArgs($command, ['--x=.25'])['--x']);
	Assert::exception(fn() => parseArgs($command, ['--x=1,5']), ParseException::class, "Option --x: expects a number, '1,5' given.");
});


test('int() and float() keep the number in the range', function () {
	$command = new Command;
	$command->addOption('--jobs', normalizer: Normalizers::int(min: 1));
	$command->addOption('--depth', normalizer: Normalizers::int(max: 3));
	$command->addOption('--ratio', normalizer: Normalizers::float(0, 1));
	Assert::same(1, parseArgs($command, ['--jobs=1'])['--jobs']);
	Assert::exception(fn() => parseArgs($command, ['--jobs=0']), ParseException::class, 'Option --jobs: expects at least 1, 0 given.');
	Assert::exception(fn() => parseArgs($command, ['--depth=4']), ParseException::class, 'Option --depth: expects at most 3, 4 given.');
	Assert::exception(fn() => parseArgs($command, ['--ratio=1.5']), ParseException::class, 'Option --ratio: expects a number from 0 to 1, 1.5 given.');
});


test('realPath() resolves the path and refuses a missing or an empty one', function () {
	$command = new Command;
	$command->addOption('--config', normalizer: Normalizers::realPath());
	Assert::same(['--config' => __FILE__], parseArgs($command, ['--config', __FILE__]));
	Assert::exception(fn() => parseArgs($command, ['--config', 'xyz']), ParseException::class, "Option --config: file path 'xyz' not found.");
	Assert::exception(fn() => parseArgs($command, ['--config=']), ParseException::class, "Option --config: file path '' not found.");
});


test('realPath() can finish a normalizer of your own', function () {
	$realPath = Normalizers::realPath();
	$command = new Command;
	$command->addOption('--test', normalizer: fn($v) => $realPath(__DIR__ . "/$v"));
	Assert::same(realpath(__DIR__ . '/bootstrap.php'), parseArgs($command, ['--test', 'bootstrap.php'])['--test']);
});
