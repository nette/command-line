<?php declare(strict_types=1);

use Nette\CommandLine\{ColorDepth, Console, Prompt};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


/** @return array{Prompt, resource} */
function prompt(string $answers, bool $terminal = true): array
{
	$input = fopen('php://memory', 'w+');
	fwrite($input, $answers);
	rewind($input);
	$output = fopen('php://memory', 'w+');
	return [new Prompt(new Console($output, ColorDepth::None, terminal: $terminal), $input, inputTerminal: $terminal), $output];
}


/** @param  resource  $output */
function written($output): string
{
	rewind($output);
	return (string) stream_get_contents($output);
}


test('the answer is one of the choices, in any case, returned as declared', function () {
	[$prompt, $output] = prompt("Y\n");
	Assert::same('y', $prompt->choose('Apply this fix?', ['y', 'n', 'a', 'q']));
	Assert::same('Apply this fix? [y/n/a/q] ', written($output));

	[$prompt, $output] = prompt("all\n");
	Assert::same('All', $prompt->choose('Apply?', ['Yes', 'All', 'no'], default: 'no'));
	Assert::same('Apply? [yes/all/NO] ', written($output)); // only the default is in capitals
});


test('an unknown answer asks again, an empty one is the default', function () {
	[$prompt, $output] = prompt("x\n\n");
	Assert::same('r', $prompt->choose('Choice', ['s', 'r', 'c'], default: 'r'));
	Assert::same('Choice [s/R/c] Choice [s/R/c] ', written($output));

	[$prompt, $output] = prompt("\nn\n"); // without a default an empty answer asks again
	Assert::same('n', $prompt->choose('Go on?', ['y', 'n']));
	Assert::same('Go on? [y/n] Go on? [y/n] ', written($output));
});


test('the end of the input is null, never the default', function () {
	[$prompt, $output] = prompt('');
	Assert::null($prompt->choose('Go on?', ['y', 'n'], default: 'y'));
	Assert::same("Go on? [Y/n] \n", written($output));

	[$prompt] = prompt("x\n"); // an unknown answer and then the end
	Assert::null($prompt->choose('Go on?', ['y', 'n']));
});


test('a map of choices is drawn as a menu above the question, which is not repeated', function () {
	[$prompt, $output] = prompt("x\ns\n");
	$choice = $prompt->choose('Choice', [
		's' => 'Skip this operation',
		'r' => 'Resume',
		'c' => 'Cancel deployment',
	], default: 'r');
	Assert::same('s', $choice);
	Assert::same(
		"  [s] Skip this operation\n"
		. "  [r] Resume\n"
		. "  [c] Cancel deployment\n"
		. 'Choice [s/R/c] Choice [s/R/c] ',
		written($output),
	);
});


test('without a user at a terminal the prompt refuses before it reads or writes', function () {
	[$prompt, $output] = prompt("y\n", terminal: false);
	Assert::false($prompt->isInteractive());
	Assert::exception(fn() => $prompt->choose('Go on?', ['y', 'n']), LogicException::class);
	Assert::same('', written($output));

	$input = fopen('php://memory', 'w+');
	$output = fopen('php://memory', 'w+');
	Assert::false((new Prompt(new Console($output, terminal: false), $input, inputTerminal: true))->isInteractive());
	Assert::false((new Prompt(new Console($output, terminal: true), $input))->isInteractive());
	Assert::true((new Prompt(new Console($output, terminal: true), $input, inputTerminal: true))->isInteractive());
});


test('an input not open for reading is refused', function () {
	$output = fopen('php://memory', 'w+');
	Assert::exception(
		fn() => new Prompt(new Console($output), fopen('php://output', 'w')),
		InvalidArgumentException::class,
		'The stream is not open for reading.',
	);
});


test('wrong choices are an error of the call', function () {
	[$prompt] = prompt('');
	Assert::exception(fn() => $prompt->choose('?', []), InvalidArgumentException::class, 'There are no choices.');
	Assert::exception(
		fn() => $prompt->choose('?', ['1' => 'One', 'n' => 'No']),
		InvalidArgumentException::class,
		'Choice 1 is a number, but a choice is a string.',
	);
	Assert::exception(
		fn() => $prompt->choose('?', [1, 2]),
		InvalidArgumentException::class,
		'Choice 1 is a number, but a choice is a string.',
	);
	Assert::exception(
		fn() => $prompt->choose('?', ['y', '']),
		InvalidArgumentException::class,
		'A choice must be a string with no surrounding whitespace.',
	);
	Assert::exception(
		fn() => $prompt->choose('?', ['y', 'Y']),
		InvalidArgumentException::class,
		"Choice 'Y' is given twice, the case aside.",
	);
	Assert::exception(
		fn() => $prompt->choose('?', ['y', 'n'], default: 'x'),
		InvalidArgumentException::class,
		"Default 'x' is not one of the choices.",
	);
});


test('the answer leaves the cursor at the start of a line, so a status needs no new line', function () {
	$input = fopen('php://memory', 'w+');
	fwrite($input, "y\n");
	rewind($input);
	$output = fopen('php://memory', 'w+');
	$console = new Console($output, ColorDepth::None, terminal: true);
	(new Prompt($console, $input, inputTerminal: true))->choose('Go on?', ['y', 'n']);
	$console->setStatus('working');
	Assert::same("Go on? [y/n] \e[?25lworking\r", written($output));
});
