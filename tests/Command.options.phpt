<?php declare(strict_types=1);

use Nette\CommandLine\{Attributes, Command, Parser, ParseResult};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


final readonly class RunOptions
{
	public function __construct(
		public ?string $config,
		/** @var list<string> */
		#[Attributes\Argument('Files or directories', repeatable: true)]
		public array $paths = [],
		#[Attributes\Option('Worker processes', valueName: 'n', normalizer: [self::class, 'parseJobs'])]
		public ?int $jobs = null,
	) {
	}


	public static function parseJobs(string $value): int
	{
		return (int) $value;
	}
}


final class OptionsHandlers
{
	/** @var list<mixed> */
	public array $calls = [];


	#[Attributes\Command]
	public function check(RunOptions $options, ParseResult $args): void
	{
		$this->calls[] = [$options, $args->command->name];
	}


	#[Attributes\Command]
	public function fix(
		RunOptions $options,
		#[Attributes\Flag('Ask about risky fixes')]
		?bool $askRisky = null,
	): void
	{
		$this->calls[] = [$options, $askRisky];
	}
}


function optionsCli(OptionsHandlers $handlers): Command
{
	$cli = new Command('tool');
	$cli->addOption('--config');
	$cli->addCommandsFrom($handlers);
	return $cli;
}


test('an object of options defines its inputs on every command that takes it', function () {
	$cli = optionsCli(new OptionsHandlers);
	[$check, $fix] = $cli->getCommands();
	Assert::same(['paths', '--jobs'], array_map(fn($input) => $input->name, $check->getParameters()));
	Assert::same(['paths', '--jobs', '--ask-risky'], array_map(fn($input) => $input->name, $fix->getParameters()));
});


test('it is built from the bound values, an ancestor option bound by name', function () {
	$handlers = new OptionsHandlers;
	$cli = optionsCli($handlers);
	(new Parser)->parse($cli, ['--config', 'c.neon', 'check', 'src', '--jobs', '4'])->run();
	(new Parser)->parse($cli, ['fix', '--ask-risky'])->run();
	Assert::equal([
		[new RunOptions('c.neon', ['src'], 4), 'check'],
		[new RunOptions(null), true],
	], $handlers->calls);
});


test('it is read only when the handler is given first', function () {
	Assert::exception(
		fn() => (new Command)->addCommand('check', handler: fn(RunOptions $options) => 0),
		InvalidArgumentException::class,
		'Parameter $options of handler closure in Command.options.phpt:%d% is an object of options RunOptions, which is only read by Command::from() and addCommandFrom().',
	);
});


test('its parameters are checked like those of a handler', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(BadOptions $options) => 0),
		InvalidArgumentException::class,
		'Parameter $jobs of BadOptions::__construct() does not accept string, which option --jobs gives as its value.',
	);
});


test('an object of options has to be one the binding can create', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(BaseOptions $options) => 0),
		InvalidArgumentException::class,
		'Parameter $options of handler closure in Command.options.phpt:%d% is an object of options BaseOptions, which cannot be created; it needs a public constructor and no abstract class.',
	);
});


abstract class BaseOptions
{
	public function __construct(
		#[Attributes\Flag]
		public ?bool $quick = null,
	) {
	}
}


final readonly class BadOptions
{
	public function __construct(
		#[Attributes\Option]
		public int $jobs,
	) {
	}
}


final readonly class LoopOptions
{
	public function __construct(
		public LoopOptions $next,
		#[Attributes\Flag]
		public ?bool $quick = null,
	) {
	}
}


final readonly class PingOptions
{
	public function __construct(
		public PongOptions $pong,
		#[Attributes\Flag]
		public ?bool $ping = null,
	) {
	}
}


final readonly class PongOptions
{
	public function __construct(
		public PingOptions $ping,
		#[Attributes\Flag]
		public ?bool $pong = null,
	) {
	}
}


test('an object of options that contains itself is refused', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(LoopOptions $options) => 0),
		InvalidArgumentException::class,
		'Parameter $next of LoopOptions::__construct() is an object of options LoopOptions, which contains itself: LoopOptions > LoopOptions.',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(PingOptions $options) => 0),
		InvalidArgumentException::class,
		'Parameter $ping of PongOptions::__construct() is an object of options PingOptions, which contains itself: PingOptions > PongOptions > PingOptions.',
	);
});


final readonly class InnerOptions
{
	public function __construct(
		#[Attributes\Flag]
		public ?bool $quick = null,
	) {
	}
}


final readonly class OuterOptions
{
	public function __construct(
		public InnerOptions $inner,
		public ParseResult $args,
		#[Attributes\Option]
		public ?string $output = null,
	) {
	}
}


test('an object of options may contain another one and the result', function () {
	$cli = (new Command)->addCommandFrom(
		#[Attributes\Command(name: 'x')] fn(OuterOptions $options) => $options->inner->quick && $options->args->wasProvided('--quick')
			? 1
			: 0,
	);
	Assert::same(['--quick', '--output'], array_map(fn($input) => $input->name, $cli->getParameters()));
	Assert::same(1, (new Parser)->parse($cli, ['x', '--quick'])->run());
});


final readonly class NormalizerOptions
{
	public function __construct(
		#[Attributes\Option(normalizer: [self::class, 'parse'])]
		public ?int $count = null,
	) {
	}


	public function parse(string $value): int
	{
		return (int) $value;
	}
}


test('a normalizer named in an attribute has to be a static method', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(NormalizerOptions $options) => 0),
		InvalidArgumentException::class,
		'Parameter $count of NormalizerOptions::__construct() has normalizer NormalizerOptions::parse(), which is no static method.',
	);
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(#[Attributes\Option(normalizer: ['Missing', 'parse'])] ?int $count = null) => 0),
		InvalidArgumentException::class,
		'Parameter $count of handler closure in Command.options.phpt:%d% has normalizer Missing::parse(), which is no static method.',
	);
});
