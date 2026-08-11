<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\Parameters\Flag;
use Nette\CommandLine\Parameters\ValueParameter;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


enum Level: int
{
	case Low = 1;
	case High = 2;
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

	Assert::exception(fn() => $verbose->alias = '-x', Error::class, 'Cannot modify readonly property Nette\CommandLine\Parameters\Flag::$alias');
	Assert::exception(fn() => $verbose->negation = '--not-verbose', Error::class, 'Cannot modify readonly property Nette\CommandLine\Parameters\Flag::$negation');
	Assert::exception(fn() => $command->name = 'other', Error::class, 'Cannot modify readonly property Nette\CommandLine\Command::$name');
	Assert::same([$verbose], $command->getParameters());
});


test('commands form a tree', function () {
	$git = new Command('git');
	$remote = $git->addCommand('remote', 'Manage remotes');
	$add = $remote->addCommand('add');

	Assert::same($remote, $git->getCommand('remote'));
	Assert::same([$remote], $git->getCommands());
	Assert::same([$remote], $git->getItems());
	Assert::same($git, $remote->getParent());
	Assert::null($git->getParent());
	Assert::same($git, $add->getRoot());
	Assert::same([$git, $remote, $add], $add->getPath());
	Assert::same('remote add', $add->getFullName());
	Assert::same('', $git->getFullName());
	Assert::exception(
		fn() => $git->getCommand('nope'),
		InvalidArgumentException::class,
		"Command 'nope' is not defined.",
	);
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
		fn() => (new Command)->addCommand('-check'),
		InvalidArgumentException::class,
		"Command name '-check' must not start with a dash.",
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
	Assert::exception(
		fn() => (new Command)->addCommand('run all'),
		InvalidArgumentException::class,
		"Command name 'run all' is not valid: start with a letter, digit or underscore and use no whitespace or control characters.",
	);
	Assert::same('--log.level', (new Command)->addOption('--log.level')->name);
	Assert::same('cache:clear', (new Command)->addCommand('cache:clear')->name);
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
	Assert::exception(fn() => $command->addOption('--no-color'), InvalidArgumentException::class, "Option '--no-color' is already defined.");

	$command = new Command;
	$command->addOption('--no-color');
	Assert::exception(fn() => $command->addFlag('--color', negatable: true), InvalidArgumentException::class, "Option '--no-color' is already defined.");
	Assert::null($command->addFlag('--colour')->negation);
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


test('a name or an alias is taken above and below, not beside', function () {
	$root = new Command;
	$root->addFlag('--verbose', alias: '-v');
	$check = $root->addCommand('check');
	$check->addFlag('--diff', alias: '-d');
	$fix = $root->addCommand('fix');
	$fix->addFlag('--diff', alias: '-d');

	Assert::exception(fn() => $root->addFlag('--verbose'), InvalidArgumentException::class, "Option '--verbose' is already defined.");
	Assert::exception(fn() => $check->addOption('--verbose'), InvalidArgumentException::class, "Option '--verbose' is already defined.");
	Assert::exception(fn() => $check->addFlag('--quiet', alias: '-v'), InvalidArgumentException::class, "Option '-v' is already defined.");
	Assert::exception(fn() => $root->addFlag('--diff'), InvalidArgumentException::class, "Option '--diff' is already defined.");
	Assert::exception(fn() => $root->addFlag('--dry-run', alias: '-d'), InvalidArgumentException::class, "Option '-d' is already defined.");
	Assert::exception(fn() => $root->addCommand('check'), InvalidArgumentException::class, "Command 'check' is already defined.");

	$fix->addArgument('paths');
	Assert::exception(fn() => $fix->addArgument('paths'), InvalidArgumentException::class, "Argument 'paths' is already defined.");
});


test('arguments and subcommands exclude each other', function () {
	$program = new Command;
	$program->addArgument('input');
	Assert::exception(
		fn() => $program->addCommand('check'),
		InvalidArgumentException::class,
		'The program takes arguments, so it cannot have subcommands.',
	);

	$program = new Command;
	$check = $program->addCommand('check');
	Assert::exception(
		fn() => $program->addArgument('input'),
		InvalidArgumentException::class,
		"The program has subcommands, so it cannot take argument 'input'.",
	);

	$check->addArgument('paths');
	Assert::exception(
		fn() => $check->addCommand('all'),
		InvalidArgumentException::class,
		"Command 'check' takes arguments, so it cannot have subcommands.",
	);
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
});


test('a value is checked and converted by its parameter, also outside the parser', function () {
	$command = new Command;
	$level = $command->addOption('--level', enum: Level::class, normalizer: fn(Level $level) => $level->name);
	Assert::same('High', $level->normalize('2'));
	Assert::exception(fn() => $level->normalize('3'), InvalidArgumentException::class, "expects 1 or 2, '3' given.");

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
