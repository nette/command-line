<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Parameters;

use Nette\CommandLine\Command;
use function count, is_array, is_string;


/**
 * A parameter with a value, which can be restricted to a list and converted.
 */
abstract class ValueParameter extends Parameter
{
	/** @var ?list<string>  the allowed values, the cases of $enumClass included */
	public readonly ?array $enum;

	/** @var ?class-string<\BackedEnum>  the enum whose case the value parses as */
	public readonly ?string $enumClass;


	/**
	 * @internal use the add*() methods of Command
	 * @param  array<mixed>|class-string<\BackedEnum>|null  $enum
	 */
	public function __construct(
		Command $command,
		string $name,
		?string $description = null,
		array|string|null $enum = null,
		/** @var ?(\Closure(mixed): mixed)  converts the value and reports a bad one by throwing */
		public readonly ?\Closure $normalizer = null,
		mixed $default = null,
		bool $repeatable = false,
	) {
		parent::__construct($command, $name, $description, $default, $repeatable);

		if ($enum === null || is_array($enum)) {
			$this->enum = $enum === null ? null : self::checkValues($enum, $name);
			$this->enumClass = null;
		} elseif (is_subclass_of($enum, \BackedEnum::class)) {
			$this->enum = array_map(fn(\BackedEnum $case) => (string) $case->value, $enum::cases());
			$this->enumClass = $enum;
		} else {
			throw new \InvalidArgumentException("Enum of $name must be a list of values or a backed enum, '$enum' given.");
		}

		if ($this->enum === []) {
			throw new \InvalidArgumentException("Enum of $name has no values.");
		} elseif (in_array('', $this->enum ?? [], true)) { // no message could show it
			throw new \InvalidArgumentException("Enum of $name has an empty value.");
		}
	}


	/**
	 * Refuses anything but a list of strings, since a number would come back as a string.
	 * @param  array<mixed>  $values
	 * @return list<string>
	 */
	private static function checkValues(array $values, string $name): array
	{
		$list = [];
		foreach ($values as $key => $value) {
			if ($key !== count($list) || !is_string($value)) {
				throw new \InvalidArgumentException("Enum of $name must be a list of strings.");
			}

			$list[] = $value;
		}

		return $list;
	}


	/**
	 * Checks a value given on the command line against the enum and converts it, to the case of the backed enum and
	 * then by the normalizer. A bad value is reported by throwing an exception.
	 */
	public function normalize(string $value): mixed
	{
		if ($this->enum !== null && !in_array($value, $this->enum, true)) {
			$enum = $this->enum;
			$last = array_pop($enum);
			throw new \InvalidArgumentException('expects ' . ($enum ? implode(', ', $enum) . " or $last" : $last) . ", '$value' given.");
		}

		$result = $value;
		foreach ($this->enumClass === null ? [] : $this->enumClass::cases() as $case) {
			if ((string) $case->value === $value) {
				$result = $case;
			}
		}

		return $this->normalizer === null ? $result : ($this->normalizer)($result);
	}
}
