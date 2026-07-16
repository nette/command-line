<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Parameters\Argument;
use Nette\CommandLine\Parameters\Flag;
use Nette\CommandLine\Parameters\Option;
use Nette\CommandLine\Parameters\Parameter;
use function func_get_args;


/**
 * The definition of a command line: a program or one of its commands, with parameters and subcommands. Commands form
 * a tree whose root is the program.
 */
final class Command
{
	private ?self $parent = null;

	/** @var list<Flag|Option|Argument|self>  in the order they were added */
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
	 * @param  bool  $valueOptional  the option may be used without its value, which then parses as true; a value is only ever attached with =
	 * @param  ?list<string>  $enum  the allowed values
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
	 * @param  ?list<string>  $enum  the allowed values
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
		if ($this->getCommands()) {
			throw new \InvalidArgumentException("{$this->getLabel()} has subcommands, so it cannot take argument '$name'.");
		} elseif (array_filter($arguments, fn(Argument $other) => $other->name === $name)) {
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
	 * Adds a command such as check in "tool check src". It inherits the options of this command.
	 */
	public function addCommand(string $name, ?string $description = null): self
	{
		NameSyntax::assertWordName($name, 'Command');
		if ($this->getArguments()) {
			throw new \InvalidArgumentException("{$this->getLabel()} takes arguments, so it cannot have subcommands.");
		} elseif (array_filter($this->getCommands(), fn(self $other) => $other->name === $name)) {
			throw new \InvalidArgumentException("Command '$name' is already defined.");
		}

		$command = new self(...func_get_args());
		$command->parent = $this;
		$this->items[] = $command;
		return $command;
	}


	public function getParent(): ?self
	{
		return $this->parent;
	}


	public function getRoot(): self
	{
		return $this->parent?->getRoot() ?? $this;
	}


	/**
	 * @return list<self>  the commands from the root down to this one
	 */
	public function getPath(): array
	{
		return [...($this->parent?->getPath() ?? []), $this];
	}


	/**
	 * Returns the names of the commands below the root down to this one, e.g. 'remote add'; empty for the root.
	 */
	public function getFullName(): string
	{
		return implode(' ', array_map(fn(self $command) => (string) $command->name, array_slice($this->getPath(), 1)));
	}


	public function getCommand(string $name): self
	{
		foreach ($this->getCommands() as $command) {
			if ($command->name === $name) {
				return $command;
			}
		}

		throw new \InvalidArgumentException("Command '$name' is not defined.");
	}


	/** @return list<self> */
	public function getCommands(): array
	{
		return array_values(array_filter($this->items, fn($item) => $item instanceof self));
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
		return array_values(array_filter($this->items, fn($item) => $item instanceof Parameter));
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
	 * @return list<Flag|Option|Argument|self>  in the order they were added
	 * @internal
	 */
	public function getItems(): array
	{
		return $this->items;
	}


	/**
	 * Refuses names or aliases taken by an option of this command, of a command above it or of a command below it.
	 * Commands beside it may reuse them.
	 * @param  list<?string>  $names
	 */
	private function assertNamesAvailable(array $names): void
	{
		foreach ([...$this->getPath(), ...$this->collectDescendants()] as $command) {
			foreach ($command->getOptions() as $other) {
				foreach ($names as $name) {
					if ($name !== null && $other->hasName($name)) {
						throw new \InvalidArgumentException("Option '$name' is already defined.");
					}
				}
			}
		}
	}


	/** @return list<self> */
	private function collectDescendants(): array
	{
		$descendants = [];
		foreach ($this->getCommands() as $command) {
			$descendants = [...$descendants, $command, ...$command->collectDescendants()];
		}

		return $descendants;
	}


	private function getLabel(): string
	{
		return $this->parent ? "Command '{$this->getFullName()}'" : 'The program';
	}
}
