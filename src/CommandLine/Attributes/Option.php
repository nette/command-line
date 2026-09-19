<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Attributes;


/**
 * Defines the parameter of a handler as an option with a value, like Command::addOption(); the name is derived from
 * the parameter, $maxWarnings is --max-warnings, and the default is the default value of the parameter.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Option
{
	public function __construct(
		public ?string $description = null,
		/** a short name such as -o */
		public ?string $alias = null,
		/** names the value in the help, e.g. 'file' shows as --output <file> */
		public ?string $valueName = null,
		/** the option may be used without its value, which then parses as true */
		public bool $valueOptional = false,
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
