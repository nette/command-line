<?php declare(strict_types=1);

use Nette\CommandLine\{ColorDepth, Command, Console, HelpRenderer};
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


test('a hidden parameter is left out', function () {
	$command = new Command('tool');
	$command->addFlag('--verbose', 'Talk more');
	$command->addFlag('--secret', 'Never shown', hidden: true);
	$command->addArgument('input', 'Input file', hidden: true);

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Options:\n"
		. "  --verbose  Talk more\n",
		renderHelp($command),
	);
});


test('a value name and a default value', function () {
	$command = new Command('tool');
	$command->addOption('--output', 'Output file', alias: '-o', valueName: 'file');
	$command->addOption('--format', 'Output format', default: 'json');
	$command->addOption('--dir', 'Where to work', default: '/tmp', defaultDescription: 'current directory');
	$command->addOption('--quiet', 'Say nothing', default: 'x', defaultDescription: '');
	$command->addOption('--level', 'How loud', enum: Level::class, default: Level::High);
	$command->addOption('--style', 'One of a, b, c', valueName: 'style', enum: ['a', 'b', 'c'], valueOptional: true);

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Options:\n"
		. "  -o, --output <file>  Output file\n"
		. "  --format <value>     Output format (default: json)\n"
		. "  --dir <value>        Where to work (default: current directory)\n"
		. "  --quiet <value>      Say nothing\n"
		. "  --level <1|2>        How loud (default: 2)\n"
		. "  --style[=style]      One of a, b, c\n",
		renderHelp($command),
	);
});


test('a default is shown only when there are words for it, and it is colored apart from the description', function () {
	$command = new Command('tool');
	$command->addFlag('--color', 'Use colors', default: true);
	$command->addOption('--tags', 'Tags', default: ['a']);
	$command->addOption('--sep', 'Separator', default: '  ');
	$command->addOption('--pad', 'Padding', default: 'x', defaultDescription: ' ');
	$command->addOption('--gap', 'Gap', defaultDescription: ' padded ');
	$command->addOption('--expr', 'Formula (default: none)', default: 'f(x) + g');

	Assert::same(
		"Usage: tool [options]\n"
		. "\n"
		. "Options:\n"
		. "  --color         Use colors\n"
		. "  --tags <value>  Tags\n"
		. "  --sep <value>   Separator\n"
		. "  --pad <value>   Padding\n"
		. "  --gap <value>   Gap (default: padded)\n"
		. "  --expr <value>  Formula (default: none) (default: f(x) + g)\n",
		renderHelp($command),
	);

	// a written "(default: …)" stays plain, the real one is colored as a whole, a ")" inside included
	Assert::contains("Formula (default: none) \e[38;5;243m(default: f(x) + g)\e[0m", renderHelp($command, colors: true));

	// a wrapped default closes its color on every line
	$colored = renderHelp($command, width: 38, colors: true);
	Assert::contains("\e[38;5;243m(default: f(x)\e[0m\n", $colored);
	Assert::contains("\e[38;5;243m+ g)\e[0m\n", $colored);
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
	$console->setColorDepth(ColorDepth::None);
	(new HelpRenderer($console, 80))->render(app());
	rewind($stream);
	Assert::same(renderHelp(app()), stream_get_contents($stream));
});
