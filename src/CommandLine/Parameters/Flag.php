<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;
use Nette\CommandLine\NameSyntax;


/**
 * A named parameter without a value such as --verbose, which parses as true when used.
 */
final class Flag extends Parameter
{
	/** @internal use Command::addFlag() */
	public function __construct(
		Command $command,
		string $name,
		?string $description = null,
		/** a short name such as -v */
		public readonly ?string $alias = null,
		/** answers on its own like --help: the parser returns it as true and every other parameter at its default, checking or converting nothing */
		public readonly bool $standalone = false,
		mixed $default = null,
		bool $repeatable = false,
	) {
		NameSyntax::assertOptionName($name, $alias);
		parent::__construct($command, $name, $description, $default, $repeatable);
	}


	/**
	 * Tells whether the flag is used by this name or its alias.
	 */
	public function hasName(string $name): bool
	{
		return $name === $this->name || $name === $this->alias;
	}
}
