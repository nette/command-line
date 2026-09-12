<?php declare(strict_types=1);

use Nette\CommandLine\{Command, Parser};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


final class HandlerSignatures
{
	public function variadic(string ...$paths): void
	{
	}


	public function withDefault(?string $output = null): void
	{
	}


	public function returnsString(): string
	{
		return '';
	}
}


test('the signature is refused when the handler is given', function () {
	$handlers = new HandlerSignatures;
	Assert::exception(
		fn() => new Command(handler: $handlers->variadic(...)),
		InvalidArgumentException::class,
		'Parameter $paths of handler HandlerSignatures::variadic() is variadic; take a repeatable input as an array.',
	);
	Assert::exception(
		fn() => (new Command)->addCommand('x', handler: function (&$value) {}),
		InvalidArgumentException::class,
		'Parameter $value of handler closure in Command.handler.phpt:%d% is passed by reference.',
	);
	Assert::exception(
		fn() => new Command(handler: $handlers->withDefault(...)),
		InvalidArgumentException::class,
		'Parameter $output of handler HandlerSignatures::withDefault() has a default value, which would never be used: it binds to an input defined elsewhere, which carries the default.',
	);
	Assert::exception(
		fn() => new Command(handler: $handlers->returnsString(...)),
		InvalidArgumentException::class,
		'Handler HandlerSignatures::returnsString() returns string; it returns an exit code, int or nothing.',
	);
});


test('exit code return types are accepted', function () {
	foreach ([fn(): int => 0, fn(): ?int => null, function (): void {}, fn(): never => exit, fn(): mixed => 0, fn() => 0] as $handler) {
		Assert::type(Command::class, new Command(handler: $handler));
	}
});


test('a refused handler leaves the tree as it was', function () {
	$cli = new Command;
	Assert::exception(fn() => $cli->addCommand('x', handler: fn(string ...$a) => 0), InvalidArgumentException::class);
	Assert::same([], $cli->getCommands());
});


test('the binding is checked when parsing, for the whole tree', function () {
	$cli = new Command('tool');
	$cli->addCommand('check', handler: fn(?string $output) => 0);
	$cli->addCommand('other');
	Assert::exception(
		fn() => (new Parser)->parse($cli, ['other']),
		InvalidArgumentException::class,
		"Command 'check': parameter \$output of handler closure in Command.handler.phpt:%d% has no input; define option --output or argument <output>, or read it from ParseResult.",
	);
});


test('an input added after the handler binds', function () {
	$cli = new Command('tool');
	$cli->addCommand('check', handler: fn(?string $output) => 0);
	$cli->addOption('--output');
	Assert::same(0, (new Parser)->parse($cli, ['check'])->run());
});


test('an option and an argument of the same name are ambiguous only when bound', function () {
	$cli = new Command('tool');
	$cli->addOption('--file');
	$check = $cli->addCommand('check');
	$check->addArgument('file', optional: true);
	Assert::same(null, (new Parser)->parse($cli, ['check'])->get('file'));

	$cli = new Command('tool');
	$cli->addOption('--file');
	$check = $cli->addCommand('check', handler: fn(?string $file) => 0);
	$check->addArgument('file', optional: true);
	Assert::exception(
		fn() => (new Parser)->parse($cli, ['check']),
		InvalidArgumentException::class,
		"Command 'check': parameter \$file of handler closure in Command.handler.phpt:%d% matches both option --file and argument <file>; read them from ParseResult.",
	);
});


test('the name is derived one way, from the parameter', function () {
	$cli = new Command('tool', handler: fn(?bool $dryRun, ?string $max_items) => 0); // dresscode:ignore naming.variable
	$cli->addFlag('--dry-run');
	$cli->addOption('--max_items');
	Assert::same(0, (new Parser)->parse($cli, [])->run());

	$cli = new Command('tool', handler: fn(?bool $v) => 0);
	$cli->addFlag('-v');
	Assert::exception(
		fn() => (new Parser)->parse($cli, []),
		InvalidArgumentException::class,
		'The program: parameter $v of handler %a% has no input; define option --v or argument <v>, or read it from ParseResult.',
	);
});


function assertTypeRefused(string $message, Closure $handler, Closure $define): void
{
	$cli = new Command('tool', handler: $handler);
	$define($cli);
	Assert::exception(
		fn() => (new Parser)->parse($cli, []),
		InvalidArgumentException::class,
		'The program: parameter $%a% of handler closure in Command.handler.phpt:%d% ' . $message,
	);
}


test('the parameter has to accept every value the input can give', function () {
	assertTypeRefused(
		'does not accept null, which option --output gives when absent.',
		fn(string $output) => 0,
		fn(Command $cli) => $cli->addOption('--output'),
	);
	assertTypeRefused(
		'does not accept int, which option --output gives when absent.',
		fn(?string $output) => 0,
		fn(Command $cli) => $cli->addOption('--output', default: 5),
	);
	assertTypeRefused(
		'does not accept true, which option --output gives when used without a value.',
		fn(?string $output) => 0,
		fn(Command $cli) => $cli->addOption('--output', valueOptional: true),
	);
	assertTypeRefused(
		'does not accept false, which option --color gives when negated.',
		fn(?true $color) => 0,
		fn(Command $cli) => $cli->addFlag('--color', negatable: true),
	);
	assertTypeRefused(
		'does not accept null, which option --jobs gives from its normalizer.',
		fn(int $jobs) => 0,
		fn(Command $cli) => $cli->addOption('--jobs', normalizer: fn($v): ?int => null, default: 1),
	);
	assertTypeRefused(
		'does not accept array, which option --tag gives as the list of its values.',
		fn(?string $tag) => 0,
		fn(Command $cli) => $cli->addOption('--tag', repeatable: true),
	);
	assertTypeRefused(
		'cannot be checked, since the normalizer of option --jobs declares no return type.',
		fn(?int $jobs) => 0,
		fn(Command $cli) => $cli->addOption('--jobs', normalizer: fn($v) => (int) $v),
	);
	assertTypeRefused(
		'has type Countable&Traversable, which cannot be checked; use a named type or a union of them.',
		fn(Countable&Traversable $items) => 0,
		fn(Command $cli) => $cli->addOption('--items'),
	);
});


test('compatible types are accepted', function () {
	$cli = new Command('tool', handler: fn(
		$untyped,
		mixed $mixed,
		string $file,
		?bool $color,
		float|string $ratio,
		iterable $tags,
		?object $date,
		?int $jobs,
	) => 0);
	$cli->addOption('--untyped');
	$cli->addOption('--mixed', normalizer: fn($v) => $v);
	$cli->addFlag('--color', negatable: true);
	$cli->addOption('--ratio', normalizer: fn($v): int => 1, default: 'auto');
	$cli->addOption('--tags', repeatable: true);
	$cli->addOption('--date', normalizer: fn($v): DateTimeImmutable => new DateTimeImmutable($v));
	$cli->addOption('--jobs', normalizer: fn($v): ?int => null);
	$cli->addArgument('file');
	Assert::same(0, (new Parser)->parse($cli, ['x'])->run());
});


final class InvokableNormalizerResult
{
	public function __invoke(): void
	{
	}
}


test('object, iterable and callable are told apart', function () {
	assertTypeRefused(
		'does not accept iterable, which option --items gives from its normalizer.',
		fn(?object $items) => 0,
		fn(Command $cli) => $cli->addOption('--items', normalizer: fn(string $v): iterable => []),
	);
	assertTypeRefused(
		'does not accept callable, which option --items gives from its normalizer.',
		fn(?object $items) => 0,
		fn(Command $cli) => $cli->addOption('--items', normalizer: fn(string $v): callable => 'strlen'),
	);

	$cli = new Command('tool', handler: fn(?callable $closure, ?callable $invokable, ?iterable $iterator) => 0);
	$cli->addOption('--closure', normalizer: fn(string $v): Closure => strlen(...));
	$cli->addOption('--invokable', normalizer: fn(string $v): InvokableNormalizerResult => new InvokableNormalizerResult);
	$cli->addOption('--iterator', normalizer: fn(string $v): ArrayIterator => new ArrayIterator);
	Assert::same(0, (new Parser)->parse($cli, [])->run());
});
