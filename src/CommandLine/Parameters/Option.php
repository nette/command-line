<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;
use Nette\CommandLine\NameSyntax;


/**
 * A named parameter with a value such as --output <file>.
 */
final class Option extends ValueParameter
{
	/**
	 * @internal use Command::addOption()
	 * @param  list<string>|class-string<\BackedEnum>|null  $enum
	 * @param  ?(\Closure(mixed): mixed)  $normalizer
	 */
	public function __construct(
		Command $command,
		string $name,
		?string $description = null,
		/** a short name such as -o */
		public readonly ?string $alias = null,
		/** the option may be used without its value, which then parses as true; a value is only ever attached with = */
		public readonly bool $valueOptional = false,
		array|string|null $enum = null,
		?\Closure $normalizer = null,
		mixed $default = null,
		bool $repeatable = false,
	) {
		NameSyntax::assertOptionName($name, $alias);
		parent::__construct($command, $name, $description, $enum, $normalizer, $default, $repeatable);
	}


	/**
	 * Tells whether the option is used by this name or its alias.
	 */
	public function hasName(string $name): bool
	{
		return $name === $this->name || $name === $this->alias;
	}
}
