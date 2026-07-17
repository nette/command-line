<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\Parameters\{Flag, ValueParameter};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('parameters are returned and can be found again', function () {
	$command = new Command('tool', 'Does things');
	$verbose = $command->addFlag('--verbose', 'Talk more', alias: '-v');
	$input = $command->addArgument('input');

	Assert::same('tool', $command->name);
	Assert::same('Does things', $command->description);
	Assert::same('Talk more', $verbose->description);
	Assert::same('-v', $verbose->alias);
	Assert::same($command, $verbose->command);
	Assert::same($verbose, $command->getParameter('--verbose'));
	Assert::same($verbose, $command->getParameter('--verbose', Flag::class));
	Assert::exception(
		fn() => $command->getParameter('--verbose', ValueParameter::class),
		InvalidArgumentException::class,
		"Parameter '--verbose' is Nette\\CommandLine\\Parameters\\Flag, not Nette\\CommandLine\\Parameters\\ValueParameter.",
	);
	Assert::same([$verbose, $input], $command->getParameters());
	Assert::same([$verbose], $command->getOptions());
	Assert::same([$input], $command->getArguments());
	Assert::exception(
		fn() => $command->getParameter('--nope'),
		InvalidArgumentException::class,
		"Parameter '--nope' is not defined.",
	);
});


test('the definition can be read but not changed from outside', function () {
	$command = new Command('tool');
	$verbose = $command->addFlag('--verbose', alias: '-v');

	Assert::exception(fn() => $verbose->alias = '-x', Error::class, 'Cannot modify readonly property Nette\CommandLine\Parameters\Flag::$alias');
	Assert::exception(fn() => $command->name = 'other', Error::class, 'Cannot modify readonly property Nette\CommandLine\Command::$name');
	Assert::same([$verbose], $command->getParameters());
});


test('names', function () {
	Assert::exception(
		fn() => (new Command)->addFlag('verbose'),
		InvalidArgumentException::class,
		"Option name 'verbose' must start with a dash, e.g. --verbose.",
	);
	Assert::exception(
		fn() => (new Command)->addOption('--output', alias: 'o'),
		InvalidArgumentException::class,
		"Alias 'o' of option --output must start with a dash.",
	);
	Assert::exception(
		fn() => (new Command)->addArgument('-input'),
		InvalidArgumentException::class,
		"Argument name '-input' must not start with a dash.",
	);
	Assert::exception(
		fn() => (new Command)->addOption('--x=y'),
		InvalidArgumentException::class,
		"Option name '--x=y' is not valid: after one or two dashes start with a letter, digit or underscore and use no '=', whitespace or control characters.",
	);
	Assert::exception(
		fn() => (new Command)->addFlag('--verbose', alias: '-v v'),
		InvalidArgumentException::class,
		"Alias '-v v' of option --verbose is not valid: after one or two dashes start with a letter, digit or underscore and use no '=', whitespace or control characters.",
	);
	Assert::exception(
		fn() => (new Command)->addArgument(''),
		InvalidArgumentException::class,
		"Argument name '' is not valid: start with a letter, digit or underscore and use no whitespace or control characters.",
	);
	Assert::same('--log.level', (new Command)->addOption('--log.level')->name);
	Assert::exception(
		fn() => new Flag(new Command, '--verbose', alias: 'v'),
		InvalidArgumentException::class,
		"Alias 'v' of option --verbose must start with a dash.",
	);
});


test('a name or an alias is taken only once', function () {
	$command = new Command;
	$command->addFlag('--verbose', alias: '-v');
	Assert::exception(fn() => $command->addFlag('--verbose'), InvalidArgumentException::class, "Option '--verbose' is already defined.");
	Assert::exception(fn() => $command->addFlag('--quiet', alias: '-v'), InvalidArgumentException::class, "Option '-v' is already defined.");

	$command->addArgument('paths');
	Assert::exception(fn() => $command->addArgument('paths'), InvalidArgumentException::class, "Argument 'paths' is already defined.");
});


test('a parameter refused is not added', function () {
	$command = new Command;
	Assert::exception(fn() => $command->addFlag('--verbose', alias: '-v v'), InvalidArgumentException::class);
	Assert::same([], $command->getItems());
	$command->addFlag('--verbose', alias: '-v');
	Assert::same(['--verbose'], array_map(fn($parameter) => $parameter->name, $command->getParameters()));
});


test('an alias is not the name', function () {
	Assert::exception(fn() => (new Command)->addOption('-o', alias: '-o'), InvalidArgumentException::class, "Alias '-o' of option -o is its name.");
	Assert::exception(fn() => (new Command)->addFlag('-v', alias: '-v'), InvalidArgumentException::class, "Alias '-v' of option -v is its name.");
});


test('an option is used by its name and its alias', function () {
	$command = new Command;
	$output = $command->addOption('--output', alias: '-o');

	Assert::true($output->hasName('--output'));
	Assert::true($output->hasName('-o'));
	Assert::false($output->hasName('--no-output'));
});


test('an enum is a list of values', function () {
	$command = new Command;
	$list = $command->addOption('--level', enum: ['low', 'high']);
	Assert::same(['low', 'high'], $list->enum);

	Assert::exception(fn() => $command->addOption('--mode', enum: []), InvalidArgumentException::class, 'Enum of --mode has no values.');
	foreach ([[1, 2], ['a' => 'x'], [true]] as $enum) {
		Assert::exception(
			fn() => $command->addOption('--mode', enum: $enum),
			InvalidArgumentException::class,
			'Enum of --mode must be a list of strings.',
		);
	}

	Assert::exception(fn() => $command->addOption('--mode', enum: ['', 'a']), InvalidArgumentException::class, 'Enum of --mode has an empty value.');
});


test('a value is checked and converted by its parameter, also outside the parser', function () {
	$command = new Command;
	$mode = $command->addArgument('mode', enum: ['fast', 'safe', 'slow']);
	Assert::same('safe', $mode->normalize('safe'));
	Assert::exception(fn() => $mode->normalize('x'), InvalidArgumentException::class, "expects fast, safe or slow, 'x' given.");
});


test('the rules that involve several arguments are judged when an argument is added', function () {
	$command = new Command;
	$command->addArgument('a', optional: true);
	Assert::exception(
		fn() => $command->addArgument('b'),
		InvalidArgumentException::class,
		"Required argument 'b' cannot follow optional argument 'a'.",
	);
	$command->addArgument('b', optional: true);
	Assert::same(['a' => null, 'b' => null], parseArgs($command, []));

	$command = new Command;
	$command->addArgument('files', optional: true, repeatable: true);
	Assert::exception(
		fn() => $command->addArgument('output', optional: true),
		InvalidArgumentException::class,
		"Argument 'output' cannot follow repeatable argument 'files', which consumes all remaining values.",
	);

	Assert::exception(
		fn() => (new Command)->addArgument('input', default: 'x'),
		InvalidArgumentException::class,
		"Argument 'input' is required, so its default value would never be used. Mark it as optional.",
	);
});
