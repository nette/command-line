<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\Help\{Section, Text};
use Nette\CommandLine\Parameters\{Flag, ValueParameter};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


enum Level: int
{
	case Low = 1;
	case High = 2;
}


enum Nothing: string
{
}


enum WithEmpty: string
{
	case Empty = '';
	case A = 'a';
}


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
	$verbose = $command->addFlag('--verbose', alias: '-v', negatable: true);

	Assert::exception(
		fn() => $verbose->alias = '-x',
		Error::class,
		'Cannot modify readonly property Nette\CommandLine\Parameters\Flag::$alias',
	);
	Assert::exception(
		fn() => $verbose->negation = '--not-verbose',
		Error::class,
		'Cannot modify readonly property Nette\CommandLine\Parameters\Flag::$negation',
	);
	Assert::exception(fn() => $command->name = 'other', Error::class, 'Cannot modify readonly property Nette\CommandLine\Command::$name');
	Assert::same([$verbose], $command->getParameters());
});


test('sections and text are items in the order they were added, the usage is not', function () {
	$command = new Command('tool');
	$command->addText('Does a thing.');
	$command->addSection('Options:');
	$verbose = $command->addFlag('--verbose');

	[$text, $section, $option] = $command->getItems();
	Assert::type(Text::class, $text);
	Assert::same('Does a thing.', $text->text);
	Assert::type(Section::class, $section);
	Assert::same('Options', $section->title); // the colon is added when it is rendered
	Assert::same($verbose, $option);

	Assert::null($command->usage);
	Assert::same(['tool [options]'], (new Command(usage: 'tool [options]'))->usage);
	Assert::same(['a', 'b'], (new Command(usage: ['a', 'b']))->usage);
});


test('the settings that shape the help only', function () {
	$option = (new Command)->addOption('--output', valueName: 'file', defaultDescription: 'current directory', hidden: true);
	Assert::same('file', $option->valueName);
	Assert::same('current directory', $option->defaultDescription);
	Assert::true($option->hidden);
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


test('a parameter refused is not added', function () {
	$command = new Command;
	Assert::exception(fn() => $command->addFlag('--verbose', alias: '-v v'), InvalidArgumentException::class);
	Assert::same([], $command->getItems());
	$command->addFlag('--verbose', alias: '-v');
	Assert::same(['--verbose'], array_map(fn($parameter) => $parameter->name, $command->getParameters()));
});


test('the negation of a flag is a name too', function () {
	Assert::exception(
		fn() => (new Command)->addFlag('-c', negatable: true),
		InvalidArgumentException::class,
		'Flag -c cannot be negated, only a flag with a long name such as --color can.',
	);

	$command = new Command;
	$color = $command->addFlag('--color', negatable: true);
	Assert::same('--no-color', $color->negation);
	Assert::exception(
		fn() => $command->addOption('--no-color'),
		InvalidArgumentException::class,
		"Option '--no-color' is already defined.",
	);

	$command = new Command;
	$command->addOption('--no-color');
	Assert::exception(
		fn() => $command->addFlag('--color', negatable: true),
		InvalidArgumentException::class,
		"Option '--no-color' is already defined.",
	);
	Assert::null($command->addFlag('--colour')->negation);
});


test('an alias is neither the name nor the negation', function () {
	Assert::exception(
		fn() => (new Command)->addOption('-o', alias: '-o'),
		InvalidArgumentException::class,
		"Alias '-o' of option -o is its name.",
	);
	Assert::exception(
		fn() => (new Command)->addFlag('-v', alias: '-v'),
		InvalidArgumentException::class,
		"Alias '-v' of option -v is its name.",
	);
	Assert::exception(
		fn() => (new Command)->addFlag('--color', alias: '--no-color', negatable: true),
		InvalidArgumentException::class,
		"Alias '--no-color' of option --color is its negation.",
	);
});


test('an option is used by its name, its alias and the negation of a flag', function () {
	$command = new Command;
	$color = $command->addFlag('--color', alias: '-c', negatable: true);
	$output = $command->addOption('--output', alias: '-o');

	Assert::true($color->hasName('--color'));
	Assert::true($color->hasName('-c'));
	Assert::true($color->hasName('--no-color'));
	Assert::false($color->hasName('--no-colour'));
	Assert::true($output->hasName('-o'));
	Assert::false($output->hasName('--no-output'));
});


test('an enum is a list of values or a backed enum', function () {
	$command = new Command;
	$list = $command->addOption('--level', enum: ['low', 'high']);
	Assert::same(['low', 'high'], $list->enum);
	Assert::null($list->enumClass);

	$backed = $command->addArgument('level', enum: Level::class);
	Assert::same(['1', '2'], $backed->enum);
	Assert::same(Level::class, $backed->enumClass);

	Assert::exception(
		fn() => $command->addOption('--mode', enum: 'stdClass'),
		InvalidArgumentException::class,
		"Enum of --mode must be a list of values or a backed enum, 'stdClass' given.",
	);
	Assert::exception(fn() => $command->addOption('--mode', enum: []), InvalidArgumentException::class, 'Enum of --mode has no values.');
	Assert::exception(
		fn() => $command->addOption('--mode', enum: Nothing::class),
		InvalidArgumentException::class,
		'Enum of --mode has no values.',
	);
	foreach ([[1, 2], ['a' => 'x'], [true]] as $enum) {
		Assert::exception(
			fn() => $command->addOption('--mode', enum: $enum),
			InvalidArgumentException::class,
			'Enum of --mode must be a list of strings.',
		);
	}

	Assert::exception(
		fn() => $command->addOption('--mode', enum: ['', 'a']),
		InvalidArgumentException::class,
		'Enum of --mode has an empty value.',
	);
	Assert::exception(
		fn() => $command->addOption('--mode', enum: WithEmpty::class),
		InvalidArgumentException::class,
		'Enum of --mode has an empty value.',
	);
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
