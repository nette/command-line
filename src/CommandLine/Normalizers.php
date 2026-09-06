<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use const INF;


/**
 * Ready-made normalizers for addOption() and addArgument().
 */
final class Normalizers
{
	/**
	 * Converts the value to an integer and refuses anything else, or a number out of the range.
	 */
	public static function int(mixed $value, ?int $min = null, ?int $max = null): int
	{
		self::assertRange($min, $max);
		$s = (string) $value;
		$digits = preg_match('#^[+-]?\d+$#D', $s)
			? (string) preg_replace(['#^\+#', '#^(-?)0+(?=\d)#', '#^-0$#D'], ['', '$1', '0'], $s)
			: '';
		$int = (int) $digits;
		if ((string) $int !== $digits) { // (int) clamps a number out of the range of int
			throw new \InvalidArgumentException('expects an integer, ' . HelpRenderer::formatCode($s) . ' given.');
		}

		self::checkRange($int, $min, $max);
		return $int;
	}


	/**
	 * Converts the value to a float and refuses anything but a decimal number, or a number out of the range.
	 */
	public static function float(mixed $value, ?float $min = null, ?float $max = null): float
	{
		self::assertRange($min, $max);
		$s = (string) $value;
		$float = (float) $s;
		if (!preg_match('#^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$#D', $s) || !is_finite($float)) { // 1e9999 overflows to infinity
			throw new \InvalidArgumentException('expects a number, ' . HelpRenderer::formatCode($s) . ' given.');
		}

		self::checkRange($float, $min, $max);
		return $float;
	}


	/** A range that no number fits is a mistake of the definition, not a bad value, so the parser does not wrap it. */
	private static function assertRange(int|float|null $min, int|float|null $max): void
	{
		if (is_nan((float) $min) || is_nan((float) $max)) {
			throw new \ValueError('A limit of the range is NAN.');
		} elseif ($min !== null && $max !== null && $min > $max) {
			throw new \ValueError("Minimum $min is greater than maximum $max.");
		} elseif ($min === INF || $max === -INF) {
			throw new \ValueError('No number fits the range.');
		}
	}


	private static function checkRange(int|float $number, int|float|null $min, int|float|null $max): void
	{
		if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
			throw new \InvalidArgumentException(match (true) {
				$max === null => "expects at least $min, `$number` given.",
				$min === null => "expects at most $max, `$number` given.",
				default => "expects a number from $min to $max, `$number` given.",
			});
		}
	}


	/**
	 * Resolves the value to an absolute path and refuses a path that does not exist.
	 */
	public static function realPath(mixed $value): string
	{
		$path = (string) $value === '' ? false : realpath((string) $value); // realpath('') is the working directory
		return $path === false
			? throw new \InvalidArgumentException('file path ' . HelpRenderer::formatCode((string) $value) . ' not found.')
			: $path;
	}
}
