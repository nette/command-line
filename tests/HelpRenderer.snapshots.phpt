<?php declare(strict_types=1);

// How the help of a program defined in code comes out at three widths and in color.

use Tester\Assert;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/fluent.php';


foreach ([30, 60, 100] as $width) {
	test("width $width", function () use ($width) {
		Assert::match((string) file_get_contents(__DIR__ . "/snapshots/fluent.$width.txt"), renderFluent($width));
	});
}


test('in color', function () {
	$colored = renderFluent(60, colors: true);
	Assert::match((string) file_get_contents(__DIR__ . '/snapshots/fluent.ansi.txt'), $colored);
	Assert::same(renderFluent(60), preg_replace('#\e\[[\d;]*m#', '', $colored));
});
