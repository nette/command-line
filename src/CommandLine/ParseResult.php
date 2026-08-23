<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function array_key_exists;


/**
 * What a command line said: the command it runs and the values of the parameters on the way to it.
 * @implements \ArrayAccess<string, mixed>
 * @implements \IteratorAggregate<string, mixed>
 */
final readonly class ParseResult implements \ArrayAccess, \IteratorAggregate
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


	/**
	 * Unlike an array, an unknown name is refused, so that a typo cannot pass for a missing value.
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return array_key_exists($offset, $this->values)
			? $this->values[$offset]
			: throw new \InvalidArgumentException("Unknown parameter '$offset'.");
	}


	/**
	 * Answers like isset() on an array: false for an unknown name and for a null value.
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->values[$offset]);
	}


	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new \LogicException('The result is read-only.');
	}


	public function offsetUnset(mixed $offset): void
	{
		throw new \LogicException('The result is read-only.');
	}


	/** @return \Generator<string, mixed> */
	public function getIterator(): \Generator
	{
		foreach ($this->values as $name => $value) {
			yield (string) $name => $value; // a numeric name such as '-1' is an integer key of the array
		}
	}
}
