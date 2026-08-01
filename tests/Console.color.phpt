<?php declare(strict_types=1);

use Nette\CommandLine\{ColorDepth, Console};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$console = new Console(colorDepth: ColorDepth::Ansi256);

Assert::same("\e[91mhello\e[0m", $console->color('red', 'hello'));
Assert::same("\e[30mhello\e[0m", $console->color('black', 'hello'));
Assert::same("\e[91;42mhello\e[0m", $console->color('red/green', 'hello'));
Assert::same("\e[91;102mhello\e[0m", $console->color('red/lime', 'hello')); // bright background no longer collapses to dark
Assert::same("\e[97;44mhello\e[0m", $console->color('white/navy', 'hello'));
Assert::same('hello', $console->color(null, 'hello')); // no color at all
Assert::same("\e[38;5;117mhello\e[0m", $console->color('117', 'hello')); // the extended palette
Assert::same("\e[38;5;0;48;5;255mhello\e[0m", $console->color('0/255', 'hello'));
Assert::same("\e[97;48;5;17mhello\e[0m", $console->color('white/17', 'hello'));

$plain = new Console(colorDepth: ColorDepth::None);
Assert::same('hello', $plain->color('red', 'hello'));
Assert::same('hello', $plain->color('#D7AF5F', 'hello'));


test('a color is drawn at the depth of the console', function () {
	Assert::same("\e[38;2;255;170;0;48;2;0;0;0mhello\e[0m", (new Console(colorDepth: ColorDepth::TrueColor))->color('#fa0/#000', 'hello'));
	Assert::same("\e[38;5;179mhello\e[0m", (new Console(colorDepth: ColorDepth::Ansi256))->color('#D7AF5F', 'hello'));
	Assert::same("\e[96;40mhello\e[0m", (new Console(colorDepth: ColorDepth::Ansi16))->color('117/#000', 'hello'));
});


Assert::exception(
	fn() => $console->color('#12345', 'hello'),
	InvalidArgumentException::class,
	"Unknown color '#12345'.",
);

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
Assert::exception( // the extended palette has 256 colors
	fn() => $console->color('256', 'hello'),
	InvalidArgumentException::class,
	"Unknown color '256'.",
);
Assert::exception(
	fn() => $console->color('-1', 'hello'),
	InvalidArgumentException::class,
	"Unknown color '-1'.",
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
