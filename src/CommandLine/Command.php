<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Parameters\{Argument, Flag, Option, Parameter};
use function func_get_args;


/**
 * The definition of a command line: the program with its parameters.
 */
final class Command
{
	/** @var list<Flag|Option|Argument>  in the order the help shows them */
	private array $items = [];


	public function __construct(
		public readonly ?string $name = null,
		public readonly ?string $description = null,
	) {
	}


	/**
	 * Adds a flag, a named parameter without a value such as --verbose. It parses as true when used, null when not.
	 * @param  ?string  $alias  a short name such as -v
	 */
	public function addFlag(
		string $name,
		?string $description = null,
		?string $alias = null,
		mixed $default = null,
		bool $repeatable = false,
	): Flag
	{
		$flag = new Flag($this, ...func_get_args());
		$this->assertNamesAvailable([$flag->name, $flag->alias]);
		return $this->items[] = $flag;
	}


	/**
	 * Adds an option with a value such as --output <file>.
	 * @param  ?string  $alias  a short name such as -o
	 * @param  bool  $valueOptional  the option may be used without its value, which then parses as true; the next token is its value only when it is one of the enum values
	 * @param  ?list<non-empty-string>  $enum  the allowed values
	 * @param  ?(\Closure(mixed): mixed)  $normalizer  converts the value and reports a bad one by throwing
	 */
	public function addOption(
		string $name,
		?string $description = null,
		?string $alias = null,
		bool $valueOptional = false,
		?array $enum = null,
		?\Closure $normalizer = null,
		mixed $default = null,
		bool $repeatable = false,
	): Option
	{
		$option = new Option($this, ...func_get_args());
		$this->assertNamesAvailable([$option->name, $option->alias]);
		return $this->items[] = $option;
	}


	/**
	 * Adds a positional argument such as <input>.
	 * @param  ?list<non-empty-string>  $enum  the allowed values
	 * @param  ?(\Closure(mixed): mixed)  $normalizer  converts the value and reports a bad one by throwing
	 */
	public function addArgument(
		string $name,
		?string $description = null,
		bool $optional = false,
		?array $enum = null,
		?\Closure $normalizer = null,
		mixed $default = null,
		bool $repeatable = false,
	): Argument
	{
		$argument = new Argument($this, ...func_get_args());

		$arguments = $this->getArguments();
		$previous = end($arguments) ?: null;
		if (array_filter($arguments, fn(Argument $other) => $other->name === $name)) {
			throw new \InvalidArgumentException("Argument '$name' is already defined.");
		} elseif (!$optional && $default !== null) {
			throw new \InvalidArgumentException("Argument '$name' is required, so its default value would never be used. Mark it as optional.");
		} elseif ($previous?->repeatable) {
			throw new \InvalidArgumentException("Argument '$name' cannot follow repeatable argument '$previous->name', which consumes all remaining values.");
		} elseif ($previous?->optional && !$optional) {
			throw new \InvalidArgumentException("Required argument '$name' cannot follow optional argument '$previous->name'.");
		}

		return $this->items[] = $argument;
	}


	/**
	 * Returns the parameter by its name; given a class, it also checks the parameter is of that kind.
	 * @template T of Parameter
	 * @param  class-string<T>  $type
	 * @return T
	 */
	public function getParameter(string $name, string $type = Parameter::class): Parameter
	{
		foreach ($this->getParameters() as $parameter) {
			if ($parameter->name === $name) {
				return $parameter instanceof $type
					? $parameter
					: throw new \InvalidArgumentException("Parameter '$name' is " . $parameter::class . ", not $type.");
			}
		}

		throw new \InvalidArgumentException("Parameter '$name' is not defined.");
	}


	/** @return list<Flag|Option|Argument> */
	public function getParameters(): array
	{
		return $this->items;
	}


	/** @return list<Flag|Option>  the named parameters, flags included */
	public function getOptions(): array
	{
		return array_values(array_filter($this->items, fn($item) => $item instanceof Flag || $item instanceof Option));
	}


	/** @return list<Argument> */
	public function getArguments(): array
	{
		return array_values(array_filter($this->items, fn($item) => $item instanceof Argument));
	}


	/**
	 * @return list<Flag|Option|Argument>  in the order the help shows them
	 * @internal
	 */
	public function getItems(): array
	{
		return $this->items;
	}


	/**
	 * Refuses names or aliases taken by an option of this command.
	 * @param  list<?string>  $names
	 */
	private function assertNamesAvailable(array $names): void
	{
		foreach ($this->getOptions() as $other) {
			foreach ($names as $name) {
				if ($name !== null && $other->hasName($name)) {
					throw new \InvalidArgumentException("Option '$name' is already defined.");
				}
			}
		}
	}
}
