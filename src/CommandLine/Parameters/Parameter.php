<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;


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
		/** how the help shows the default: null derives it from a scalar value, a string puts it in words, a blank one leaves it out */
		public readonly ?string $defaultDescription = null,
		/** the values are collected into a list */
		public readonly bool $repeatable = false,
		/** left out of the help */
		public readonly bool $hidden = false,
	) {
	}
}
