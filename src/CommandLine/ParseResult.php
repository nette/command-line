<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function array_key_exists;


/**
 * What a command line said: the command it runs and the values of the parameters on the way to it.
 * @implements \IteratorAggregate<string, mixed>
 */
final readonly class ParseResult implements \IteratorAggregate
{
	/** @internal */
	public function __construct(
		public Command $command,
		/** @var array<string, mixed> */
		private array $values,
		/** @var array<string, true>  names of the parameters that occurred on the command line */
		private array $provided = [],
		private bool $empty = false,
	) {
	}


	/**
	 * Returns the value of the parameter. An unknown name is refused, so that a typo cannot pass for a missing value.
	 */
	public function get(string $name): mixed
	{
		return array_key_exists($name, $this->values)
			? $this->values[$name]
			: throw new \InvalidArgumentException("Unknown parameter '$name'.");
	}


	/**
	 * Tells whether nothing at all was given on the command line.
	 */
	public function isEmpty(): bool
	{
		return $this->empty;
	}


	/**
	 * Tells whether the parameter was given on the command line, so that a value equal to the default can be told
	 * from the default itself.
	 */
	public function wasProvided(string $name): bool
	{
		return array_key_exists($name, $this->values)
			? isset($this->provided[$name])
			: throw new \InvalidArgumentException("Unknown parameter '$name'.");
	}


	/** @return array<string, mixed> */
	public function toArray(): array
	{
		return $this->values;
	}


	/** @return \Generator<string, mixed> */
	public function getIterator(): \Generator
	{
		foreach ($this->values as $name => $value) {
			yield (string) $name => $value; // a numeric name such as '-1' is an integer key of the array
		}
	}
}
