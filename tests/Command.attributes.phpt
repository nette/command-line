<?php declare(strict_types=1);

use Nette\CommandLine\{Attributes, Command, Parser, ParseResult};
use Nette\CommandLine\Parameters\{Argument, Flag, Option};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


enum AttributeFormat: string
{
	case Console = 'console';
	case Json = 'json';
}


final class AttributeHandlers
{
	/** @var list<mixed> */
	public array $calls = [];


	#[Attributes\Command('Print the configuration', usage: 'tool config [options]')]
	public function config(
		ParseResult $args,
		#[Attributes\Option('Only these', valueName: 'name', repeatable: true)]
		array $only = [],
		#[Attributes\Option('One file', alias: '-f', valueName: 'path')]
		?string $file = null,
		#[Attributes\Option('Output format')]
		AttributeFormat $format = AttributeFormat::Console,
		#[Attributes\Flag('Print more', negatable: true)]
		?bool $verbose = null,
	): int
	{
		$this->calls[] = [$only, $file, $format, $verbose, $args->wasProvided('--format')];
		return 0;
	}


	#[Attributes\Command('Translate a configuration')]
	public function import(
		#[Attributes\Argument('Configuration file')]
		string $source,
		#[Attributes\Argument('Where to write it')]
		?string $target = null,
	): void
	{
		$this->calls[] = [$source, $target];
	}


	#[Attributes\Command]
	public function plain(
		#[Attributes\Option('Limit', name: '--max')]
		?string $limit,
	): void
	{
		$this->calls[] = $limit;
	}
}


test('the attributes define the command and its inputs, in the order of the parameters', function () {
	$handlers = new AttributeHandlers;
	$cli = new Command('tool');
	$config = $cli->addCommandFrom($handlers->config(...));
	Assert::same('config', $config->name);
	Assert::same('Print the configuration', $config->description);
	Assert::same(['tool config [options]'], $config->usage);
	Assert::same(['--only', '--file', '--format', '--verbose'], array_map(fn($input) => $input->name, $config->getParameters()));

	[$only, $file, $format, $verbose] = $config->getParameters();
	Assert::type(Option::class, $only);
	Assert::true($only->repeatable);
	Assert::same([], $only->default);
	Assert::same('-f', $file->alias);
	Assert::same(AttributeFormat::class, $format->enumClass);
	Assert::same(AttributeFormat::Console, $format->default);
	Assert::type(Flag::class, $verbose);
	Assert::true($verbose->negatable);
});


test('the bound values come from the inputs the attributes defined', function () {
	$handlers = new AttributeHandlers;
	$cli = new Command('tool');
	$cli->addCommandFrom($handlers->config(...));
	(new Parser)->parse($cli, ['config', '--only', 'a', '-f', 'x.php', '--format', 'json', '--no-verbose'])->run();
	(new Parser)->parse($cli, ['config'])->run();
	Assert::same([
		[['a'], 'x.php', AttributeFormat::Json, false, true],
		[[], null, AttributeFormat::Console, null, false],
	], $handlers->calls);
});


test('an argument is optional when its parameter has a default value', function () {
	$handlers = new AttributeHandlers;
	$cli = new Command('tool');
	$import = $cli->addCommandFrom($handlers->import(...));
	[$source, $target] = $import->getArguments();
	Assert::type(Argument::class, $source);
	Assert::false($source->optional);
	Assert::true($target->optional);
	(new Parser)->parse($cli, ['import', 'a.xml'])->run();
	Assert::same([['a.xml', null]], $handlers->calls);
});


test('an input takes the name the attribute gives it', function () {
	$handlers = new AttributeHandlers;
	$cli = new Command('tool');
	$plain = $cli->addCommandFrom($handlers->plain(...));
	Assert::same(['--max'], array_map(fn($input) => $input->name, $plain->getParameters()));
	(new Parser)->parse($cli, ['plain', '--max', '5'])->run();
	Assert::same(['5'], $handlers->calls);
});


test('a closure declares its inputs as well', function () {
	$cli = new Command('tool');
	$cli->addCommandFrom(#[Attributes\Command(name: 'copy')] fn(
		#[Attributes\Flag('Dry run')]
		?bool $dryRun,
		#[Attributes\Argument('Paths', repeatable: true)]
		array $paths = [],
	) => $dryRun ? count($paths) : 0);
	Assert::same(2, (new Parser)->parse($cli, ['copy', '--dry-run', 'a', 'b'])->run());
});


test('a closure names its command by the attribute', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn() => 0),
		InvalidArgumentException::class,
		'A closure or __invoke() given to addCommandFrom() has no name to derive the command from; give it by name: of the attribute #[Command].',
	);
});


test('Command::from() creates the program the attributes describe, and runs its handler', function () {
	$handlers = new AttributeHandlers;
	$cli = Command::from($handlers->config(...));
	Assert::null($cli->getParent());
	Assert::same('Print the configuration', $cli->description);
	Assert::same(['tool config [options]'], $cli->usage);
	Assert::same(['--only', '--file', '--format', '--verbose'], array_map(fn($input) => $input->name, $cli->getParameters()));
	(new Parser)->parse($cli, ['--only', 'a', '--format', 'json'])->run();
	Assert::same([[['a'], null, AttributeFormat::Json, null, true]], $handlers->calls);
});


test('the program takes its name from the attribute alone, a method or a closure names no program', function () {
	$handlers = new AttributeHandlers;
	Assert::null(Command::from($handlers->config(...))->name);
	Assert::null(Command::from(fn(#[Attributes\Flag] ?bool $verbose) => 0)->name);
	Assert::same('tool', Command::from(#[Attributes\Command(name: 'tool')] fn() => 0)->name);
});


test('the commands of a program made by Command::from() inherit its options', function () {
	$cli = Command::from(fn(
		#[Attributes\Option('Configuration file', alias: '-c')]
		?string $config = null,
	) => 1);
	$cli->addCommandFrom(#[Attributes\Command(name: 'check')] fn(?string $config) => $config === 'x.neon' ? 2 : 0);
	Assert::same(1, (new Parser)->parse($cli, [])->run());
	Assert::same(2, (new Parser)->parse($cli, ['-c', 'x.neon', 'check'])->run());
});


test('the attributes of a handler given by handler: are refused, not skipped', function () {
	$handlers = new AttributeHandlers;
	Assert::exception(
		fn() => (new Command)->addCommand('other', handler: $handlers->config(...)),
		InvalidArgumentException::class,
		'Attribute #[Command] of handler AttributeHandlers::config() is only read by Command::from() and addCommandFrom().',
	);
	Assert::exception(
		fn() => new Command('tool', handler: fn(#[Attributes\Flag] ?bool $verbose) => 0),
		InvalidArgumentException::class,
		'Attribute #[Flag] of handler closure in Command.attributes.phpt:%d% is only read by Command::from() and addCommandFrom().',
	);
});


test('a refused input of the handler leaves the tree as it was', function () {
	$cli = new Command('tool');
	$cli->addOption('--file');
	Assert::exception(
		fn() => $cli->addCommandFrom(#[Attributes\Command(name: 'x')] fn(#[Attributes\Option] ?string $file) => 0),
		InvalidArgumentException::class,
		"Option '--file' is already defined.",
	);
	Assert::exception(
		fn() => $cli->addCommandFrom(
			#[Attributes\Command(name: 'x')] fn(#[Attributes\Option] ?string $other, #[Attributes\Option] string $count) => 0,
		),
		InvalidArgumentException::class,
		'Parameter $count of handler closure in Command.attributes.phpt:%d% does not accept null, which option --count gives when absent.',
	);
	Assert::same([], $cli->getCommands());
});


test('attributes in the wrong place or a wrong combination are refused', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(#[Attributes\Option] fn() => 0),
		InvalidArgumentException::class,
		'Attribute #[Option] of handler closure in Command.attributes.phpt:%d% belongs to a parameter.',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(#[Attributes\Command] $a) => 0),
		InvalidArgumentException::class,
		'Parameter $a of handler closure in Command.attributes.phpt:%d% has attribute #[Command], which belongs to the handler.',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(#[Attributes\Flag] #[Attributes\Option] $a) => 0),
		InvalidArgumentException::class,
		'Parameter $a of handler closure in Command.attributes.phpt:%d% has more than one of the attributes #[Flag], #[Option] and #[Argument].',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(#[Attributes\Option] ParseResult $args) => 0),
		InvalidArgumentException::class,
		'Parameter $args of handler closure in Command.attributes.phpt:%d% is given by its type, so it defines no input.',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(#[Attributes\Option] ?DateTimeImmutable $date = new DateTimeImmutable) => 0),
		InvalidArgumentException::class,
		'Parameter $date of handler closure in Command.attributes.phpt:%d% has an object as its default value, which every run would share.',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(#[Attributes\Argument(optional: false)] ?string $file = null) => 0),
		InvalidArgumentException::class,
		'Parameter $file of handler closure in Command.attributes.phpt:%d% defines a required argument, so its default value would never be used.',
	);
});


test('an input of the handler binds by its own name, an ancestor option by the derived one', function () {
	$cli = new Command('tool');
	$cli->addOption('--config');
	$cli->addCommandFrom(
		#[Attributes\Command(name: 'x')] fn(?string $config, #[Attributes\Option(name: '--out')] ?string $output = null) => $config === 'c'
			&& $output === 'o'
			? 1
			: 0,
	);
	Assert::same(1, (new Parser)->parse($cli, ['x', '--config', 'c', '--out', 'o'])->run());
});


test('addCommandsFrom() adds every public method with #[Command], in the order of the class', function () {
	$handlers = new class {
		#[Attributes\Command]
		public function first(#[Attributes\Flag] ?bool $quick = null): int
		{
			return $quick ? 1 : 0;
		}


		public function helper(): void
		{
		}


		#[Attributes\Command]
		public static function second(): int
		{
			return 2;
		}
	};
	$cli = new Command('tool');
	$commands = $cli->addCommandsFrom($handlers);
	Assert::same(['first', 'second'], array_map(fn($command) => $command->name, $commands));
	Assert::same($commands, $cli->getCommands());
	Assert::same(1, (new Parser)->parse($cli, ['first', '--quick'])->run());
	Assert::same(2, (new Parser)->parse($cli, ['second'])->run());
});


final class PrivateHandlers
{
	public function register(Command $cli): void
	{
		$cli->addCommandFrom($this->secret(...));
	}


	#[Attributes\Command]
	private function secret(): int
	{
		return 3;
	}
}


test('a method with #[Command] that is not public is refused by addCommandsFrom(), handed over by addCommandFrom()', function () {
	Assert::exception(
		fn() => (new Command)->addCommandsFrom(new PrivateHandlers),
		InvalidArgumentException::class,
		'Method PrivateHandlers::secret() has the attribute #[Command] but is not public; addCommandsFrom() adds public methods only.',
	);

	$cli = new Command('tool');
	(new PrivateHandlers)->register($cli);
	Assert::same(3, (new Parser)->parse($cli, ['secret'])->run());
});


test('the settings of an attribute are those of its add*() method, without the name and the default', function () {
	foreach ([
		[Attributes\Flag::class, 'addFlag'],
		[Attributes\Option::class, 'addOption'],
		[Attributes\Argument::class, 'addArgument'],
		[Attributes\Command::class, 'addCommand'],
	] as [$attribute, $method]) {
		$settings = array_map(fn($parameter) => $parameter->name, (new ReflectionMethod($attribute, '__construct'))->getParameters());
		$arguments = array_map(fn($parameter) => $parameter->name, (new ReflectionMethod(Command::class, $method))->getParameters());
		$expected = array_values(array_diff($arguments, ['name', 'default', 'handler']));
		Assert::same($expected, array_values(array_diff($settings, ['name'])), $attribute);
		Assert::same('name', end($settings), $attribute);
	}
});


test('the name of a command is derived from its method, name: overrides it', function () {
	$handlers = new class {
		#[Attributes\Command('Rewrite the comments')]
		public function migrateSuppressions(): void
		{
		}


		#[Attributes\Command(name: 'self:update')]
		public function selfUpdate(): void
		{
		}
	};
	Assert::same(
		['migrate-suppressions', 'self:update'],
		array_map(fn($command) => $command->name, (new Command)->addCommandsFrom($handlers)),
	);
});


test('a method given first without #[Command] is named after itself', function () {
	$handlers = new class {
		public function status(#[Attributes\Flag] ?bool $short = null): int
		{
			return $short ? 1 : 0;
		}
	};
	$cli = new Command('tool');
	$status = $cli->addCommandFrom($handlers->status(...));
	Assert::same('status', $status->name);
	Assert::null($status->description);
	Assert::same(1, (new Parser)->parse($cli, ['status', '--short'])->run());
});


test('__invoke() names no command', function () {
	$handler = new class {
		public function __invoke(): void
		{
		}
	};
	Assert::exception(
		fn() => (new Command)->addCommandFrom($handler(...)),
		InvalidArgumentException::class,
		'A closure or __invoke() given to addCommandFrom() has no name to derive the command from; give it by name: of the attribute #[Command].',
	);
});


class BaseHandlers
{
	#[Attributes\Command]
	public function inherited(): void
	{
	}
}


final class ChildHandlers extends BaseHandlers
{
	#[Attributes\Command]
	public function own(): void
	{
	}


	#[Attributes\Command]
	public function broken(string ...$paths): void
	{
	}
}


test('addCommandsFrom() adds the own methods before the inherited ones, each command on its own', function () {
	$cli = new Command('tool');
	Assert::exception(fn() => $cli->addCommandsFrom(new ChildHandlers), InvalidArgumentException::class, '%a% is variadic; %a%');
	Assert::same(['own'], array_map(fn($command) => $command->name, $cli->getCommands()));
});


test('every setting of an attribute reaches the input', function () {
	$cli = (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(
		#[Attributes\Flag('Help', standalone: true, hidden: true)]
		?bool $help,
		#[Attributes\Option('Level', defaultDescription: 'all')]
		?string $level = null,
		#[Attributes\Argument('Path', optional: true)]
		?string $path = null,
	) => 0);
	[$help, $level, $path] = $cli->getParameters();
	Assert::true($help->standalone);
	Assert::true($help->hidden);
	Assert::same('all', $level->defaultDescription);
	Assert::true($path->optional);
});
