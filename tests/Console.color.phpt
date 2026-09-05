<?php declare(strict_types=1);

use Nette\CommandLine\Console;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$console = new Console(colors: true);

Assert::same("\e[91mhello\e[0m", $console->color('red', 'hello'));
Assert::same("\e[30mhello\e[0m", $console->color('black', 'hello'));
Assert::same("\e[91;42mhello\e[0m", $console->color('red/green', 'hello'));
Assert::same("\e[91;102mhello\e[0m", $console->color('red/lime', 'hello')); // bright background no longer collapses to dark
Assert::same("\e[97;44mhello\e[0m", $console->color('white/navy', 'hello'));
Assert::same('hello', $console->color(null, 'hello')); // no color at all

$plain = new Console(colors: false);
Assert::same('hello', $plain->color('red', 'hello'));

Assert::exception(
	fn() => $console->color('pink', 'hello'),
	InvalidArgumentException::class,
	"Unknown color 'pink'.",
);
Assert::exception(
	fn() => $console->color('red/pink', 'hello'),
	InvalidArgumentException::class,
	"Unknown color 'pink'.",
);
Assert::exception( // a name is refused even when the color would not be used
	fn() => $plain->color('pink', 'hello'),
	InvalidArgumentException::class,
	"Unknown color 'pink'.",
);
Assert::exception( // a color is a foreground and at most a background
	fn() => $console->color('red/green/blue', 'hello'),
	InvalidArgumentException::class,
	"Color 'red/green/blue' is a foreground name, or 'foreground/background'.",
);
Assert::exception(
	fn() => $console->color('red/', 'hello'),
	InvalidArgumentException::class,
	"Color 'red/' is a foreground name, or 'foreground/background'.",
);
