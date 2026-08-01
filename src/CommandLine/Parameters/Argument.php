<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;
use Nette\CommandLine\NameSyntax;


/**
 * A positional parameter such as <input> or [output].
 */
final class Argument extends ValueParameter
{
	/**
	 * @internal use Command::addArgument()
	 * @param  list<string>|class-string<\BackedEnum>|null  $enum
	 * @param  ?(\Closure(mixed): mixed)  $normalizer
	 */
	public function __construct(
		Command $command,
		string $name,
		?string $description = null,
		/** the argument may be left out */
		public readonly bool $optional = false,
		array|string|null $enum = null,
		?\Closure $normalizer = null,
		mixed $default = null,
		bool $repeatable = false,
	) {
		NameSyntax::assertWordName($name, 'Argument');
		parent::__construct($command, $name, $description, $enum, $normalizer, $default, $repeatable);
	}
}
