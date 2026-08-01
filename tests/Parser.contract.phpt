<?php declare(strict_types=1);

// The contract of a value: an optional value says what happens when an option is present without it,
// the default value says what is returned when the option is absent, and the two are independent.

use Nette\CommandLine\Command;
use Nette\CommandLine\ParseException;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('flag: absent, bare, repeated', function () {
	$command = new Command;
	$command->addFlag('--x');
	Assert::same(['--x' => null], parseArgs($command, []));
	Assert::same(['--x' => true], parseArgs($command, ['--x']));
	Assert::same(['--x' => true], parseArgs($command, ['--x', '--x']));
	Assert::exception(fn() => parseArgs($command, ['--x=1']), ParseException::class, 'Option --x does not accept a value.');

	$repeatable = new Command;
	$repeatable->addFlag('--x', repeatable: true);
	Assert::same(['--x' => []], parseArgs($repeatable, []));
	Assert::same(['--x' => [true, true]], parseArgs($repeatable, ['--x', '--x']));
});


test('only the last value counts, the earlier ones are not even checked', function () {
	$command = new Command;
	$command->addOption('--x', enum: ['ok']);
	Assert::same(['--x' => 'ok'], parseArgs($command, ['--x=bad', '--x=ok']));
	Assert::exception(fn() => parseArgs($command, ['--x=ok', '--x=bad']), ParseException::class, "Option --x: expects ok, 'bad' given.");
});


test('optional value: the sentinel is not the default', function () {
	$command = new Command;
	$command->addOption('--x', valueOptional: true, default: 'fb');
	Assert::same(['--x' => 'fb'], parseArgs($command, []));
	Assert::same(['--x' => true], parseArgs($command, ['--x']));
	Assert::same(['--x' => 'v'], parseArgs($command, ['--x=v']));
	Assert::exception(fn() => parseArgs($command, ['--x', 'v']), ParseException::class, 'Unexpected argument v.'); // only attached
	Assert::same(['--x' => 'second'], parseArgs($command, ['--x=first', '--x=second']));

	$repeatable = new Command;
	$repeatable->addOption('--x', valueOptional: true, repeatable: true);
	Assert::same(['--x' => []], parseArgs($repeatable, []));
	Assert::same(['--x' => ['a', true]], parseArgs($repeatable, ['--x=a', '--x']));
});


test('an optional value takes the next token only when it is one of the enum values', function () {
	$command = new Command;
	$command->addOption('--color', valueOptional: true, enum: ['always', 'never']);
	$command->addArgument('input', optional: true);
	Assert::same(['--color' => 'never', 'input' => null], parseArgs($command, ['--color', 'never']));
	Assert::same(['--color' => 'always', 'input' => null], parseArgs($command, ['--color=always']));
	Assert::same(['--color' => true, 'input' => 'src'], parseArgs($command, ['--color', 'src'])); // not a value of its own
	Assert::same(['--color' => true, 'input' => null], parseArgs($command, ['--color']));
});


test('required value: the default applies when the option is absent', function () {
	$command = new Command;
	$command->addOption('--x', default: 'fb');
	Assert::same(['--x' => 'fb'], parseArgs($command, []));
	Assert::same(['--x' => 'v'], parseArgs($command, ['--x', 'v']));
	Assert::same(['--x' => 'v'], parseArgs($command, ['--x=v']));
	Assert::same(['--x' => 'second'], parseArgs($command, ['--x=first', '--x=second']));
	Assert::exception(fn() => parseArgs($command, ['--x']), ParseException::class, 'Option --x requires a value.');

	$noDefault = new Command;
	$noDefault->addOption('--x');
	Assert::same(['--x' => null], parseArgs($noDefault, []));

	$repeatable = new Command;
	$repeatable->addOption('--x', default: 'fb', repeatable: true);
	Assert::same(['--x' => ['fb']], parseArgs($repeatable, []));
	Assert::same(['--x' => ['a', 'b']], parseArgs($repeatable, ['--x=a', '--x=b']));
});


test('argument: optional and required', function () {
	$command = new Command;
	$command->addArgument('a', optional: true, default: 'fb');
	Assert::same(['a' => 'fb'], parseArgs($command, []));
	Assert::same(['a' => 'v'], parseArgs($command, ['v']));

	$required = new Command;
	$required->addArgument('a');
	Assert::exception(fn() => parseArgs($required, []), ParseException::class, 'Missing required argument <a>.');
});


test('a normalizer may return null', function () {
	$command = new Command;
	$command->addOption('--x', normalizer: fn() => null, default: 'fb');
	Assert::same(['--x' => null], parseArgs($command, ['--x', 'v']));
	Assert::same(['--x' => 'fb'], parseArgs($command, []));
});


test('the default is returned as it is', function () {
	$command = new Command;
	$command->addOption('--x', enum: ['a'], normalizer: strtoupper(...), default: 'raw');
	Assert::same(['--x' => 'raw'], parseArgs($command, []));
});


test('the enum is checked before the normalizer, and neither sees the sentinel', function () {
	$command = new Command;
	$command->addOption('--x', valueOptional: true, enum: ['a'], normalizer: fn() => 'normalized');
	Assert::same(['--x' => 'normalized'], parseArgs($command, ['--x=a']));
	Assert::same(['--x' => true], parseArgs($command, ['--x']));
	Assert::exception(fn() => parseArgs($command, ['--x=b']), ParseException::class, "Option --x: expects a, 'b' given.");
});


test('an exception from the normalizer names the parameter and the command', function () {
	$command = new Command;
	$command->addOption('--x', normalizer: fn() => throw new Exception('boom'));
	$e = Assert::exception(fn() => parseArgs($command, ['--x=v']), ParseException::class, 'Option --x: boom');
	Assert::same('boom', $e->getPrevious()?->getMessage());
	Assert::same($command, $e->command);

	$argument = new Command;
	$argument->addArgument('a', normalizer: fn() => throw new Exception('boom'));
	Assert::exception(fn() => parseArgs($argument, ['v']), ParseException::class, 'Argument <a>: boom');
});


test('a ParseException from the normalizer passes through, an Error does not', function () {
	$command = new Command;
	$command->addOption('--x', normalizer: fn() => throw new ParseException('exact wording', $command));
	Assert::exception(fn() => parseArgs($command, ['--x=v']), ParseException::class, 'exact wording');

	$error = new Command;
	$error->addOption('--x', normalizer: fn() => throw new TypeError('broken normalizer'));
	Assert::exception(fn() => parseArgs($error, ['--x=v']), TypeError::class, 'broken normalizer');
});
