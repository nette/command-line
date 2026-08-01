<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * Ready-made normalizers for addOption() and addArgument().
 */
final class Normalizers
{
	/**
	 * Converts the value to an integer and refuses anything else, or a number out of the range.
	 * @return \Closure(mixed): int
	 */
	public static function int(?int $min = null, ?int $max = null): \Closure
	{
		return function (mixed $value) use ($min, $max): int {
			$s = (string) $value;
			$int = preg_match('#^[+-]?\d+$#D', $s)
				? filter_var((string) preg_replace('#^([+-]?)0+(?=\d)#', '$1', $s), FILTER_VALIDATE_INT)
				: false;
			if ($int === false) {
				throw new \InvalidArgumentException("expects an integer, '$s' given.");
			}

			self::checkRange($int, $min, $max);
			return $int;
		};
	}


	/**
	 * Converts the value to a float and refuses anything but a decimal number, or a number out of the range.
	 * @return \Closure(mixed): float
	 */
	public static function float(?float $min = null, ?float $max = null): \Closure
	{
		return function (mixed $value) use ($min, $max): float {
			$s = (string) $value;
			if (!preg_match('#^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$#D', $s)) {
				throw new \InvalidArgumentException("expects a number, '$s' given.");
			}

			$float = (float) $s;
			self::checkRange($float, $min, $max);
			return $float;
		};
	}


	private static function checkRange(int|float $number, int|float|null $min, int|float|null $max): void
	{
		if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
			throw new \InvalidArgumentException(match (true) {
				$max === null => "expects at least $min, $number given.",
				$min === null => "expects at most $max, $number given.",
				default => "expects a number from $min to $max, $number given.",
			});
		}
	}


	/**
	 * Resolves the value to an absolute path and refuses a path that does not exist.
	 * @return \Closure(mixed): string
	 */
	public static function realPath(): \Closure
	{
		return function (mixed $value): string {
			$path = (string) $value === '' ? false : realpath((string) $value); // realpath('') is the working directory
			return $path === false
				? throw new \InvalidArgumentException("file path '$value' not found.")
				: $path;
		};
	}
}
