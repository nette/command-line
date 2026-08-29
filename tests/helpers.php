<?php declare(strict_types=1);

// Small helpers shared by the tests.

use Nette\CommandLine\Command;
use Nette\CommandLine\Console;
use Nette\CommandLine\HelpRenderer;
use Nette\CommandLine\Parser;


/**
 * @param  list<string>  $args
 * @return array<string, mixed>
 */
function parseArgs(Command $command, array $args): array
{
	return (new Parser)->parse($command, $args)->toArray();
}


function renderHelp(Command $command, int $width = 80, bool $colors = false): string
{
	return (new HelpRenderer(new Console(colors: $colors), $width))->renderToString($command);
}
