<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;


/**
 * A parameter with a value, which can be restricted to a list and converted.
 */
abstract class ValueParameter extends Parameter
{
	/** @var ?list<string>  the allowed values */
	public readonly ?array $enum;


	/**
	 * @internal use the add*() methods of Command
	 * @param  ?list<string>  $enum
	 */
	public function __construct(
		Command $command,
		string $name,
		?string $description = null,
		?array $enum = null,
		/** @var ?(\Closure(mixed): mixed)  converts the value and reports a bad one by throwing */
		public readonly ?\Closure $normalizer = null,
		mixed $default = null,
		bool $repeatable = false,
	) {
		parent::__construct($command, $name, $description, $default, $repeatable);
		$this->enum = $enum === null ? null : array_map(strval(...), $enum);
	}


	/**
	 * Checks a value given on the command line against the enum and converts it by the normalizer. A bad value is
	 * reported by throwing an exception.
	 */
	public function normalize(string $value): mixed
	{
		if ($this->enum !== null && !in_array($value, $this->enum, true)) {
			$enum = $this->enum;
			$last = array_pop($enum);
			throw new \InvalidArgumentException('expects ' . ($enum ? implode(', ', $enum) . " or $last" : $last) . ", '$value' given.");
		}

		return $this->normalizer === null ? $value : ($this->normalizer)($value);
	}
}
