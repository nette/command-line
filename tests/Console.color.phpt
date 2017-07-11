<?php

use Nette\CommandLine\Console;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$console = new Console;
$console->useColors();

Assert::same("\x1b[m", $console->color(NULL));
Assert::same("\x1b[1;31m", $console->color('red'));
Assert::same("\x1b[1;31;42m", $console->color('red/green'));
Assert::same("\x1b[1;31;42m", $console->color('red/lime'));

Assert::same("\x1b[mhello\x1b[0m", $console->color(NULL, 'hello'));
Assert::same("\x1b[1;31mhello\x1b[0m", $console->color('red', 'hello'));
Assert::same("\x1b[1;31;42mhello\x1b[0m", $console->color('red/green', 'hello'));
Assert::same("\x1b[1;31;42mhello\x1b[0m", $console->color('red/lime', 'hello'));
