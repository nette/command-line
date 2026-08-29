<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;
use function is_bool, is_scalar;


/**
 * An input of a command: a flag, an option with a value or a positional argument.
 */
abstract class Parameter
{
	/** @internal use the add*() methods of Command */
	public function __construct(
		public readonly Command $command,
		public readonly string $name,
		public readonly ?string $description = null,
		/** what the parameter is worth when it is absent from the command line, never checked or converted */
		public readonly mixed $default = null,
		/** the values are collected into a list */
		public readonly bool $repeatable = false,
	) {
	}


	/**
	 * Returns the default value in words, as the help shows it: a scalar value or the value of an enum case, and null
	 * when there is nothing to show.
	 */
	public function describeDefault(): ?string
	{
		$value = $this->default;
		return match (true) {
			$value instanceof \BackedEnum => (string) $value->value,
			is_scalar($value) && !is_bool($value) => (string) $value,
			default => null,
		};
	}
}
