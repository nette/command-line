<?php declare(strict_types=1);

/**
 * @phpVersion >= 8.6
 */

use Nette\CommandLine\{Command, Normalizers, ParseException};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('a partial application sets the range of int() and float()', function () {
	$command = new Command;
	$command->addOption('--jobs', normalizer: Normalizers::int(?, min: 1));
	$command->addOption('--ratio', normalizer: Normalizers::float(?, 0, 1));
	Assert::same(['--jobs' => 4, '--ratio' => 0.5], parseArgs($command, ['--jobs=4', '--ratio=.5']));
	Assert::exception(fn() => parseArgs($command, ['--jobs=0']), ParseException::class, 'Option `--jobs`: expects at least 1, `0` given.');
});
