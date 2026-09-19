<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Attributes;


/**
 * Defines the parameter of a handler as a flag, like Command::addFlag(); the name is derived from the parameter,
 * $dryRun is --dry-run, and the default is the default value of the parameter.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Flag
{
	public function __construct(
		public ?string $description = null,
		/** a short name such as -v */
		public ?string $alias = null,
		/** answers on its own like --help: the other flags are taken as given, the rest of the line is neither checked nor converted */
		public bool $standalone = false,
		/** --no-name turns a flag with a long name off, which parses as false */
		public bool $negatable = false,
		/** the default in words for the help; a blank one leaves it out */
		public ?string $defaultDescription = null,
		public bool $repeatable = false,
		public bool $hidden = false,
		/** the name when it is not the one derived from the parameter */
		public ?string $name = null,
	) {
	}
}
