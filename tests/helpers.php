<?php declare(strict_types=1);

// Small helpers shared by the tests.

use Nette\CommandLine\Command;
use Nette\CommandLine\Parser;


/**
 * @param  list<string>  $args
 * @return array<string, mixed>
 */
function parseArgs(Command $command, array $args): array
{
	return (new Parser)->parse($command, $args)->toArray();
}
