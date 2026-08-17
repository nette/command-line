<?php declare(strict_types=1);

// How many colors a console takes: the stream decides, NO_COLOR, FORCE_COLOR and COLORTERM override it and the
// application wins over all.

use Nette\CommandLine\{ColorDepth, Console};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


// the tests run piped, so no console of theirs is over a terminal
putenv('NO_COLOR');
putenv('FORCE_COLOR');
putenv('COLORTERM');


test('no env, not a terminal', function () {
	putenv('NO_COLOR');
	putenv('FORCE_COLOR');
	Assert::false((new Console)->hasColors());
	Assert::true((new Console(terminal: true))->hasColors());
});


test('FORCE_COLOR forces colors on', function () {
	putenv('NO_COLOR');
	putenv('FORCE_COLOR=1');
	Assert::true((new Console)->hasColors());
});


test('FORCE_COLOR=0 and =false force colors off', function () {
	putenv('NO_COLOR');
	putenv('FORCE_COLOR=0');
	Assert::false((new Console(terminal: true))->hasColors());

	putenv('FORCE_COLOR=false');
	Assert::false((new Console(terminal: true))->hasColors());
});


test('empty NO_COLOR does not disable colors', function () {
	putenv('NO_COLOR=');
	putenv('FORCE_COLOR=1');
	Assert::true((new Console)->hasColors());
});


test('non-empty NO_COLOR wins over FORCE_COLOR', function () {
	putenv('NO_COLOR=1');
	putenv('FORCE_COLOR=1');
	Assert::false((new Console)->hasColors());
});


test('what the application says wins over the environment', function () {
	putenv('NO_COLOR=1');
	putenv('FORCE_COLOR');
	Assert::true((new Console(colorDepth: ColorDepth::Ansi256))->hasColors());

	putenv('NO_COLOR');
	putenv('FORCE_COLOR=1');
	Assert::false((new Console(colorDepth: ColorDepth::None))->hasColors());
});


test('a terminal takes the extended palette, and any color when COLORTERM says so', function () {
	putenv('NO_COLOR');
	putenv('FORCE_COLOR');
	Assert::same(ColorDepth::Ansi256, (new Console(terminal: true))->getColorDepth());

	putenv('COLORTERM=truecolor');
	Assert::same(ColorDepth::TrueColor, (new Console(terminal: true))->getColorDepth());
	putenv('COLORTERM=24bit');
	Assert::same(ColorDepth::TrueColor, (new Console(terminal: true))->getColorDepth());
	Assert::same(ColorDepth::None, (new Console)->getColorDepth(), 'a stream that is not a terminal takes nothing');
	putenv('COLORTERM');
});


test('the level of FORCE_COLOR is the least depth', function () {
	putenv('NO_COLOR');
	foreach ([
		'1' => ColorDepth::Ansi16,
		'true' => ColorDepth::Ansi16,
		'2' => ColorDepth::Ansi256,
		'3' => ColorDepth::TrueColor,
		'4' => ColorDepth::TrueColor,
	] as $level => $depth) {
		putenv("FORCE_COLOR=$level");
		Assert::same($depth, (new Console)->getColorDepth());
	}

	putenv('FORCE_COLOR=1');
	Assert::same(ColorDepth::Ansi256, (new Console(terminal: true))->getColorDepth(), 'the terminal takes more');
	putenv('COLORTERM=truecolor');
	Assert::same(ColorDepth::TrueColor, (new Console)->getColorDepth());
	putenv('COLORTERM');
});


putenv('NO_COLOR');
putenv('FORCE_COLOR');
