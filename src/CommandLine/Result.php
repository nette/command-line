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
final class Result implements \ArrayAccess, \IteratorAggregate
{
	/** @internal */
	public function __construct(
		public readonly Command $command,
		/** @var array<string, mixed> */
		private readonly array $values,
		/** @var list<string>  names of the parameters that occurred on the command line */
		private readonly array $provided = [],
		private readonly bool $empty = false,
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
			? in_array($name, $this->provided, true)
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
	public function offsetGet(mixed $name): mixed
	{
		return array_key_exists($name, $this->values)
			? $this->values[$name]
			: throw new \InvalidArgumentException("Unknown parameter '$name'.");
	}


	/**
	 * Answers like isset() on an array: false for an unknown name and for a null value.
	 */
	public function offsetExists(mixed $name): bool
	{
		return isset($this->values[$name]);
	}


	public function offsetSet(mixed $name, mixed $value): void
	{
		throw new \LogicException('The result is read-only.');
	}


	public function offsetUnset(mixed $name): void
	{
		throw new \LogicException('The result is read-only.');
	}


	/** @return \ArrayIterator<string, mixed> */
	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->values);
	}
}
