<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Attributes;


/**
 * Defines the parameter of a handler as a positional argument, like Command::addArgument(); the name is derived from
 * the parameter, the default is the default value of the parameter and makes the argument optional.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Argument
{
	public function __construct(
		public ?string $description = null,
		/** null makes the argument optional when the parameter has a default value */
		public ?bool $optional = null,
		/**
		 * the allowed values, or a backed enum whose case the value parses as; a parameter typed by a backed enum takes its cases
		 * @var list<non-empty-string>|class-string<\BackedEnum>|null
		 */
		public array|string|null $enum = null,
		/**
		 * converts the value and reports a bad one by throwing; a static method as [class, name] can be written by any PHP
		 * @var (\Closure(mixed): mixed)|array{class-string, string}|null
		 */
		public \Closure|array|null $normalizer = null,
		/** the default in words for the help; a blank one leaves it out */
		public ?string $defaultDescription = null,
		public bool $repeatable = false,
		public bool $hidden = false,
		/** the name when it is not the one derived from the parameter */
		public ?string $name = null,
	) {
	}
}
