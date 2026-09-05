<?php declare(strict_types=1);

// Whether a console colors: the stream decides, NO_COLOR and FORCE_COLOR override it and the application wins over all.

use Nette\CommandLine\Console;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


// the tests run piped, so no console of theirs is over a terminal
putenv('NO_COLOR');
putenv('FORCE_COLOR');


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
	Assert::true((new Console(colors: true))->hasColors());

	putenv('NO_COLOR');
	putenv('FORCE_COLOR=1');
	Assert::false((new Console(colors: false))->hasColors());
});


putenv('NO_COLOR');
putenv('FORCE_COLOR');
