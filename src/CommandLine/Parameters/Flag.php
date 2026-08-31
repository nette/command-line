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
	/** the name that turns the flag off, such as --no-color, or null when the flag is not negatable */
	public readonly ?string $negation;


	/** @internal use Command::addFlag() */
	public function __construct(
		Command $command,
		string $name,
		?string $description = null,
		/** a short name such as -v */
		public readonly ?string $alias = null,
		/** answers on its own like --help: the parser returns it as true and every other parameter at its default, checking or converting nothing */
		public readonly bool $standalone = false,
		/** --no-name turns the flag off, which parses as false */
		public readonly bool $negatable = false,
		mixed $default = null,
		?string $defaultDescription = null,
		bool $repeatable = false,
		bool $hidden = false,
	) {
		NameSyntax::assertOptionName($name, $alias);
		if ($negatable && !str_starts_with($name, '--')) {
			throw new \InvalidArgumentException("Flag $name cannot be negated, only a flag with a long name such as --color can.");
		}

		parent::__construct($command, $name, $description, $default, $defaultDescription, $repeatable, $hidden);
		$this->negation = $negatable ? '--no-' . substr($name, 2) : null;
	}


	/**
	 * Tells whether the flag is used by this name, its alias or its negation.
	 */
	public function hasName(string $name): bool
	{
		return $name === $this->name || $name === $this->alias || $name === $this->negation;
	}
}
