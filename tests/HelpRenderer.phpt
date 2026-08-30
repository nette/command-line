<?php declare(strict_types=1);

use Nette\CommandLine\Command;
use Nette\CommandLine\Console;
use Nette\CommandLine\HelpRenderer;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


enum Level: int
{
	case Low = 1;
	case High = 2;
}


function app(bool $commandRequired = false): Command
{
	$cli = new Command('dresscode', commandRequired: $commandRequired);
	$cli->addFlag('--help', 'Print this help');
	$check = $cli->addCommand('check', 'Report violations');
	$check->addArgument('paths', optional: true, repeatable: true);
	$check->addFlag('--generate-baseline', 'Write the baseline');
	$explain = $cli->addCommand('explain', 'What a rule is for');
	$explain->addArgument('rule');
	return $cli;
}


test('the help of a program', function () {
	$command = new Command('convert');
	$command->addFlag('--verbose', 'Enable verbose mode', alias: '-v');
	$command->addOption('--output', 'Output file', alias: '-o');
	$command->addOption('--format', 'Output format', alias: '-f', valueOptional: true, enum: ['json', 'xml']);
	$command->addArgument('input', 'Input file');

	Assert::same(
		"Usage: convert [options] <input>\n"
		. "\n"
		. "Options:\n"
		. "  -v, --verbose            Enable verbose mode\n"
		. "  -o, --output <value>     Output file\n"
		. "  -f, --format[=json|xml]  Output format\n"
		. "\n"
		. "Arguments:\n"
		. "  <input>                  Input file\n",
		renderHelp($command),
	);
});


test('sections and text', function () {
	$command = new Command('tool');
	$command->addText('Does a thing.');
	$command->addSection('Options');
	$command->addFlag('--verbose', 'Talk more');

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Does a thing.\n"
		. "\n"
		. "Options:\n"
		. "  --verbose  Talk more\n",
		renderHelp($command),
	);
});


test('the usage may be given by hand', function () {
	$command = new Command(usage: ['tool check [paths...]', 'tool fix [paths...]']);
	$command->addFlag('--verbose');

	Assert::same(
		"Usage:\n"
		. "  tool check [paths...]\n"
		. "  tool fix [paths...]\n"
		. "\n"
		. "Options:\n"
		. "  --verbose\n",
		renderHelp($command),
	);
});


test('a default value', function () {
	$command = new Command('tool');
	$command->addOption('--format', 'Output format', default: 'json');
	$command->addOption('--level', 'How loud', enum: Level::class, default: Level::High);

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Options:\n"
		. "  --format <value>  Output format (default: json)\n"
		. "  --level <1|2>     How loud (default: 2)\n",
		renderHelp($command),
	);
});


test('syntax too long for the column continues on the next line', function () {
	$command = new Command('tool');
	$command->addFlag('--short', 'Short one');
	$command->addOption('--a-very-long-option-name-indeed', 'Long one');

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Options:\n"
		. "  --short  Short one\n"
		. "  --a-very-long-option-name-indeed <value>\n"
		. "           Long one\n",
		renderHelp($command),
	);
});


test('a heading goes in front of every run of one kind', function () {
	$command = new Command('tool');
	$command->addFlag('--one');
	$command->addArgument('input');
	$command->addFlag('--two');

	Assert::same(
		"Usage: tool [options] <input>\n"
		. "\n"
		. "Options:\n"
		. "  --one\n"
		. "\n"
		. "Arguments:\n"
		. "  <input>\n"
		. "\n"
		. "Options:\n"
		. "  --two\n",
		renderHelp($command),
	);
});


test('the description of the command follows its usage', function () {
	$command = new Command('tool', 'Does a thing.');
	$command->addFlag('--verbose');
	Assert::same("Usage: tool [options]\n\nDoes a thing.\n\nOptions:\n  --verbose\n", renderHelp($command));

	$command = new Command('tool', 'Does a thing.', usage: 'tool [--verbose]');
	$command->addFlag('--verbose');
	Assert::same("Usage: tool [--verbose]\n\nDoes a thing.\n\nOptions:\n  --verbose\n", renderHelp($command));

	$command = new Command('tool', "Does a thing\n\tand then another.");
	Assert::same("Usage: tool\n\nDoes a thing and\nthen another.\n", renderHelp($command, 20));
});


test('when two columns do not fit, the description goes below the syntax', function () {
	$command = new Command('tool');
	$command->addOption('--output', 'Where the converted files are written', alias: '-o');

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Options:\n"
		. "  -o, --output <value>\n"
		. "    Where the converted\n"
		. "    files are written\n",
		renderHelp($command, 24),
	);
});


test('a negatable flag shows both forms', function () {
	$command = new Command('tool');
	$command->addFlag('--color', 'Use colors', alias: '-c', negatable: true);
	Assert::same("Usage: tool [options]\n\nOptions:\n  -c, --[no-]color  Use colors\n", renderHelp($command));
});


test('a program with commands lists them with their arguments', function () {
	Assert::same(
		"Usage: dresscode [options] [command]\n"
		. "\n"
		. "Options:\n"
		. "  --help            Print this help\n"
		. "\n"
		. "Commands:\n"
		. "  check [paths]...  Report violations\n"
		. "  explain <rule>    What a rule is for\n",
		renderHelp(app()),
	);

	Assert::match("Usage: dresscode [options] <command>\n%A%", renderHelp(app(commandRequired: true)));
});


test('a command shows its own options apart from the inherited ones', function () {
	Assert::same(
		"Usage: dresscode check [options] [paths]...\n"
		. "\n"
		. "Report violations\n"
		. "\n"
		. "Arguments:\n"
		. "  [paths]...\n"
		. "\n"
		. "Options:\n"
		. "  --generate-baseline  Write the baseline\n"
		. "\n"
		. "Global options:\n"
		. "  --help               Print this help\n",
		renderHelp(app()->getCommand('check')),
	);

	Assert::same(
		"Usage: dresscode explain [options] <rule>\n"
		. "\n"
		. "What a rule is for\n"
		. "\n"
		. "Arguments:\n"
		. "  <rule>\n"
		. "\n"
		. "Options:\n"
		. "  --help  Print this help\n",
		renderHelp(app()->getCommand('explain')),
	);
});


test('a colored help is the plain one in escape sequences', function () {
	$check = app()->getCommand('check');
	Assert::same(renderHelp($check), preg_replace('#\e\[[\d;]*m#', '', renderHelp($check, colors: true)));
});


test('renderToString() without a console draws any command plain', function () {
	$renderer = new HelpRenderer(width: 80);
	Assert::same(renderHelp(app()), $renderer->renderToString(app()));
	Assert::same(renderHelp(app()->getCommand('check')), $renderer->renderToString(app()->getCommand('check')));
});


test('render() writes the help to the console', function () {
	$stream = fopen('php://memory', 'w+');
	$console = new Console($stream);
	$console->useColors(false);
	(new HelpRenderer($console, 80))->render(app());
	rewind($stream);
	Assert::same(renderHelp(app()), stream_get_contents($stream));
});
