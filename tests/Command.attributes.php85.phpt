<?php declare(strict_types=1);

/**
 * @phpVersion >= 8.5
 */

use Nette\CommandLine\{Attributes, Command, Parser};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('a normalizer is a static function in the attribute', function () {
	$cli = (new Command)->addCommandFrom(#[Attributes\Command(name: 'x')] fn(
		#[Attributes\Option('Limit', normalizer: static function (string $value): int { return (int) $value; })]
		?int $limit,
	) => $limit ?? -1);
	Assert::same(5, (new Parser)->parse($cli, ['x', '--limit', '5'])->run());
	Assert::same(-1, (new Parser)->parse($cli, ['x'])->run());
});


test('its declared return type is checked against the parameter', function () {
	Assert::exception(
		fn() => (new Command)->addCommandFrom(fn(
			#[Attributes\Option(normalizer: static function (string $value): int { return (int) $value; })]
			string $limit,
		) => 0),
		InvalidArgumentException::class,
		'Parameter $limit of handler closure in Command.attributes.php85.phpt:%d% does not accept int, which option --limit gives from its normalizer.',
	);
});
