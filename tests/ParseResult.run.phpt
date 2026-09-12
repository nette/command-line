<?php declare(strict_types=1);

use Nette\CommandLine\{Command, Parser, ParseResult};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


enum RunFormat: string
{
	case Console = 'console';
	case Json = 'json';
}


final class RunHandlers
{
	/** @var list<mixed> */
	public array $calls = [];


	public function check(array $paths, ?bool $dryRun, ?int $maxWarnings, ?RunFormat $format): int
	{
		$this->calls[] = [$paths, $dryRun, $maxWarnings, $format];
		return 1;
	}


	public function explain(string $rule, ParseResult $args, Command $command): void
	{
		$this->calls[] = [$rule, $args->get('--verbose'), $command->name];
	}
}


function runCli(RunHandlers $handlers): Command
{
	$cli = new Command('tool');
	$cli->addFlag('--verbose');
	$cli->addFlag('--help', standalone: true);
	$check = $cli->addCommand('check', handler: $handlers->check(...));
	$check->addFlag('--dry-run');
	$check->addOption('--max-warnings', normalizer: fn(string $value): int => (int) $value);
	$check->addOption('--format', enum: RunFormat::class);
	$check->addArgument('paths', optional: true, repeatable: true);
	$cli->addCommand('explain', handler: $handlers->explain(...))
		->addArgument('rule');
	return $cli;
}


test('the parameters get the inputs they are named after', function () {
	$handlers = new RunHandlers;
	$code = (new Parser)->parse(runCli($handlers), ['check', 'src', '--dry-run', '--max-warnings', '5', '--format', 'json'])->run();
	Assert::same(1, $code);
	Assert::same([[['src'], true, 5, RunFormat::Json]], $handlers->calls);
});


test('absent inputs give their defaults', function () {
	$handlers = new RunHandlers;
	(new Parser)->parse(runCli($handlers), ['check'])->run();
	Assert::same([[[], null, null, null]], $handlers->calls);
});


test('the result and the command are given by type, an option of the program by name', function () {
	$handlers = new RunHandlers;
	$code = (new Parser)->parse(runCli($handlers), ['--verbose', 'explain', 'rule'])->run();
	Assert::same(0, $code);
	Assert::same([['rule', true, 'explain']], $handlers->calls);
});


test('a closure handler of the program', function () {
	$cli = new Command('tool', handler: fn(?string $output) => $output === null ? 3 : 4);
	$cli->addOption('--output');
	Assert::same(3, (new Parser)->parse($cli, [])->run());
	Assert::same(4, (new Parser)->parse($cli, ['--output', 'x'])->run());
});


test('a standalone answer is not run', function () {
	$handlers = new RunHandlers;
	$result = (new Parser)->parse(runCli($handlers), ['explain', '--help']);
	Assert::exception(
		fn() => $result->run(),
		LogicException::class,
		'A standalone flag answered, so there is nothing to run; handle it before calling run().',
	);
	Assert::same([], $handlers->calls);
});


test('a command without a handler is not run', function () {
	$handlers = new RunHandlers;
	Assert::exception(
		fn() => (new Parser)->parse(runCli($handlers), [])->run(),
		LogicException::class,
		'The program has no handler to run.',
	);

	$cli = new Command('tool');
	$cli->addCommand('remote')->addCommand('add');
	Assert::exception(
		fn() => (new Parser)->parse($cli, ['remote'])->run(),
		LogicException::class,
		"Command 'remote' has no handler to run.",
	);
});


test('a value other than an exit code is refused', function () {
	$cli = new Command('tool', handler: fn() => false);
	Assert::exception(
		fn() => (new Parser)->parse($cli, [])->run(),
		LogicException::class,
		'Handler closure in ParseResult.run.phpt:%d% returned false; it returns an exit code, int or nothing.',
	);
});


test('the handler is called strictly, without coercion', function () {
	$cli = new Command('tool', handler: fn(int|string $count) => is_int($count) ? 1 : 2);
	$cli->addOption('--count', default: 'none');
	Assert::same(2, (new Parser)->parse($cli, ['--count', '5'])->run());
});


test('an exception of the handler passes through', function () {
	$cli = new Command('tool', handler: fn() => throw new RuntimeException('failed'));
	Assert::exception(fn() => (new Parser)->parse($cli, [])->run(), RuntimeException::class, 'failed');
});
