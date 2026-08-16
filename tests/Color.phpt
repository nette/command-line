<?php declare(strict_types=1);

// One color drawn as near as a depth allows.

use Nette\CommandLine\{Color, ColorDepth};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('a name is drawn by its code at every depth', function () {
	foreach ([ColorDepth::Ansi16, ColorDepth::Ansi256, ColorDepth::TrueColor] as $depth) {
		Assert::same('91', Color::parse('red')->toSgr($depth));
		Assert::same('101', Color::parse('red')->toSgr($depth, background: true));
	}
});


test('an index stays one where the palette is taken', function () {
	Assert::same('38;5;117', Color::parse('117')->toSgr(ColorDepth::Ansi256));
	Assert::same('38;5;117', Color::parse('117')->toSgr(ColorDepth::TrueColor));
	Assert::same('48;5;0', Color::parse('0')->toSgr(ColorDepth::Ansi256, background: true));
});


test('a hex is exact in true color and the nearest of the palette below it', function () {
	Assert::same('38;2;215;175;95', Color::parse('#D7AF5F')->toSgr(ColorDepth::TrueColor));
	Assert::same('48;2;255;170;0', Color::parse('#fa0')->toSgr(ColorDepth::TrueColor, background: true));
	Assert::same('38;5;179', Color::parse('#D7AF5F')->toSgr(ColorDepth::Ansi256), 'a point of the cube');
	Assert::same('38;5;179', Color::parse('#D4A55A')->toSgr(ColorDepth::Ansi256), 'the nearest point of the cube');
	Assert::same('38;5;244', Color::parse('#808080')->toSgr(ColorDepth::Ansi256), 'a gray is nearer among the grays');
	Assert::same('38;5;16', Color::parse('#000')->toSgr(ColorDepth::Ansi256));
});


test('at the depth of 16 colors a hue keeps its hue and a gray stays gray', function () {
	$pastels = ['#D7AF5F' => '93', '#87AFD7' => '94', '#87D787' => '92', '#D78787' => '91', '#87D7FF' => '96', '#D787D7' => '95'];
	foreach ($pastels as $color => $code) {
		Assert::same($code, Color::parse($color)->toSgr(ColorDepth::Ansi16), $color);
	}

	Assert::same('31', Color::parse('#800000')->toSgr(ColorDepth::Ansi16), 'a dark hue gets the dark name');
	Assert::same('34', Color::parse('#0000FF')->toSgr(ColorDepth::Ansi16));
	Assert::same('96', Color::parse('117')->toSgr(ColorDepth::Ansi16));
	Assert::same('91', Color::parse('9')->toSgr(ColorDepth::Ansi16), 'an index of the 16 is that color');
	Assert::same('90', Color::parse('#767676')->toSgr(ColorDepth::Ansi16));
	Assert::same('37', Color::parse('#D0D0D0')->toSgr(ColorDepth::Ansi16));
	Assert::same('40', Color::parse('#000')->toSgr(ColorDepth::Ansi16, background: true));
	Assert::same('97', Color::parse('#FFF')->toSgr(ColorDepth::Ansi16));
});


test('an unknown color is refused', function () {
	foreach (['pink', '256', '-1', '0117', '#12345', '#GGG', 'D7AF5F'] as $color) {
		Assert::exception(
			fn() => Color::parse($color),
			InvalidArgumentException::class,
			"Unknown color '$color'.",
		);
	}
});
