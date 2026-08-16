<?php declare(strict_types=1);

// Small helpers shared by the tests.

use Nette\CommandLine\{ColorDepth, Command, Console, HelpRenderer};


function renderHelp(Command $command, int $width = 80, bool $colors = false): string
{
	return (new HelpRenderer(new Console(colorDepth: $colors ? ColorDepth::Ansi256 : ColorDepth::None), $width))->renderToString($command);
}
