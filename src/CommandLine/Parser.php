<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\CommandLine;


/**
 * Stupid command line arguments parser.
 */
class Parser
{
	public const
		Argument = 'argument',
		Optional = 'optional',
		Repeatable = 'repeatable',
		Enum = 'enum',
		RealPath = 'realpath',
		Normalizer = 'normalizer',
		Default = 'default';

	#[\Deprecated('use Parser::Argument')]
	public const ARGUMENT = self::Argument;

	#[\Deprecated('use Parser::Optional')]
	public const OPTIONAL = self::Optional;

	#[\Deprecated('use Parser::Repeatable')]
	public const REPEATABLE = self::Repeatable;

	#[\Deprecated('use Parser::Enum')]
	public const ENUM = self::Enum;

	#[\Deprecated('use Parser::RealPath')]
	public const REALPATH = self::RealPath;

	#[\Deprecated('use Parser::Default')]
	public const VALUE = self::Default;
	private const OptionPresent = true;

	/** @var array<string, Option> */
	private array $options = [];
	private string $help = '';

	/** @var string[] */
	private array $args;


	public function __construct(string $help = '', array $defaults = [])
	{
		$this->args = isset($_SERVER['argv']) ? array_slice($_SERVER['argv'], 1) : [];

		if ($help || $defaults) {
			$this->addFromHelp($help, $defaults);
		}
	}


	/**
	 * Extracts option definitions from formatted help text.
	 */
	public function addFromHelp(string $help, array $defaults = []): static
	{
		preg_match_all('#^[ \t]+(--?\w.*?)(?:  .*\(default: (.*)\)|  |\r|$)#m', $help, $lines, PREG_SET_ORDER);
		foreach ($lines as $line) {
			preg_match_all('#(--?\w[\w-]*)(?:[= ](<.*?>|\[.*?]|\w+)(\.{0,3}))?[ ,|]*#A', $line[1], $m);
			if (!count($m[0]) || count($m[0]) > 2 || implode('', $m[0]) !== $line[1]) {
				throw new \InvalidArgumentException("Unable to parse '$line[1]'.");
			}

			$name = end($m[1]);
			$defaults[$name] = ($defaults[$name] ?? []) + [
				self::Argument => (bool) end($m[2]),
				self::Optional => isset($line[2]) || (str_starts_with(end($m[2]), '[')),
				self::Repeatable => (bool) end($m[3]),
				self::Enum => count($enums = explode('|', trim(end($m[2]), '<[]>'))) > 1 ? $enums : null,
				self::Default => $line[2] ?? null,
			];
			$aliases[$name] = $name !== $m[1][0] ? $m[1][0] : null;
		}

		foreach ($defaults as $name => $opt) {
			$default = $opt[self::Default] ?? null;
			if ($opt[self::RealPath] ?? false) {
				$opt[self::Normalizer] = ($opt[self::Normalizer] ?? null)
					? fn($value) => self::normalizeRealPath($opt[self::Normalizer]($value))
					: self::normalizeRealPath(...);
			}
			$this->options[$name] = new Option(
				name: $name,
				alias: $aliases[$name] ?? null,
				type: match (true) {
					!($opt[self::Argument] ?? true) => ValueType::None,
					($opt[self::Optional] ?? false) || $default !== null => ValueType::Optional,
					default => ValueType::Required,
				},
				repeatable: (bool) ($opt[self::Repeatable] ?? null),
				fallback: $default,
				normalizer: $opt[self::Normalizer] ?? null,
				enum: $opt[self::Enum] ?? null,
			);
		}

		$this->help .= $help;
		return $this;
	}


	/**
	 * Adds a switch (flag without value), e.g. --foo or -f.
	 * Parses as true when used, null when not.
	 */
	public function addSwitch(
		string $name,
		?string $alias = null,
		bool $repeatable = false,
	): static
	{
		$this->options[$name] = new Option(
			name: $name,
			alias: $alias,
			type: ValueType::None,
			repeatable: $repeatable,
		);
		return $this;
	}


	/**
	 * Adds an option with value, e.g. --foo json or -f json.
	 * @param bool $optionalValue  If true, value can be omitted (--foo parses as true)
	 * @param mixed $fallback      Parsed value when option is not used at all
	 */
	public function addOption(
		string $name,
		?string $alias = null,
		bool $optionalValue = false,
		mixed $fallback = null,
		?array $enum = null,
		bool $repeatable = false,
		?\Closure $normalizer = null,
	): static
	{
		$this->options[$name] = new Option(
			name: $name,
			alias: $alias,
			type: $optionalValue ? ValueType::Optional : ValueType::Required,
			fallback: $fallback,
			repeatable: $repeatable,
			enum: $enum,
			normalizer: $normalizer,
		);
		return $this;
	}


	/**
	 * Adds a positional argument, e.g. <foo> or [foo].
	 * @param bool $optional   If true, argument can be omitted
	 * @param mixed $fallback  Parsed value when argument is not provided
	 */
	public function addArgument(
		string $name,
		bool $optional = false,
		mixed $fallback = null,
		?array $enum = null,
		bool $repeatable = false,
		?\Closure $normalizer = null,
	): static
	{
		$this->options[$name] = new Option(
			name: $name,
			type: $optional ? ValueType::Optional : ValueType::Required,
			fallback: $fallback,
			repeatable: $repeatable,
			enum: $enum,
			normalizer: $normalizer,
		);
		return $this;
	}


	/**
	 * Parses command-line arguments and returns associative array of values.
	 * @param array|null $args  Arguments to parse (defaults to $_SERVER['argv'])
	 */
	public function parse(?array $args = null): array
	{
		$args ??= $this->args;

		$aliases = $positional = [];
		foreach ($this->options as $opt) {
			if ($opt->positional) {
				$positional[] = $opt;
			} elseif ($opt->alias !== null) {
				$aliases[$opt->alias] = $opt;
			}
		}

		$params = [];
		reset($positional);
		$i = 0;
		while ($i < count($args)) {
			$arg = $args[$i++];
			if ($arg[0] !== '-') {
				if (!current($positional)) {
					throw new \Exception("Unexpected parameter $arg.");
				}

				$opt = current($positional);
				$arg = $this->normalizeValue($opt, $arg);
				if (!$opt->repeatable) {
					$params[$opt->name] = $arg;
					next($positional);
				} else {
					$params[$opt->name][] = $arg;
				}

				continue;
			}

			[$name, $arg] = strpos($arg, '=') ? explode('=', $arg, 2) : [$arg, self::OptionPresent];
			$opt = $aliases[$name] ?? $this->options[$name] ?? null;
			if (!$opt) {
				throw new \Exception("Unknown option $name.");
			}

			if ($arg !== self::OptionPresent && $opt->type === ValueType::None) {
				throw new \Exception("Option $opt->name has not argument.");

			} elseif ($arg === self::OptionPresent && $opt->type !== ValueType::None) {
				if (isset($args[$i]) && $args[$i][0] !== '-') {
					$arg = $args[$i++];
				} elseif ($opt->type === ValueType::Required) {
					throw new \Exception("Option $opt->name requires argument.");
				}
			}

			$arg = $this->normalizeValue($opt, $arg);

			if (!$opt->repeatable) {
				$params[$opt->name] = $arg;
			} else {
				$params[$opt->name][] = $arg;
			}
		}

		foreach ($this->options as $opt) {
			if (isset($params[$opt->name])) {
				continue;
			} elseif ($opt->type !== ValueType::Required) {
				$params[$opt->name] = $opt->fallback;
			} elseif ($opt->positional) {
				throw new \Exception("Missing required argument <$opt->name>.");
			} else {
				$params[$opt->name] = null;
			}

			if ($opt->repeatable) {
				$params[$opt->name] = (array) $params[$opt->name];
			}
		}

		return $params;
	}


	/**
	 * Parses only specified options, ignoring everything else.
	 * No validation, no exceptions. Useful for early-exit options like --help.
	 * @param  string[]  $names  Option names to parse (e.g., ['--help', '--version'])
	 * @return array<string, mixed>  Parsed values (null if option not used)
	 */
	public function parseOnly(array $names, ?array $args = null): array
	{
		$args ??= $this->args;
		$lookup = [];
		foreach ($names as $name) {
			$opt = $this->options[$name] ?? null;
			if ($opt) {
				$lookup[$name] = $opt;
				if ($opt->alias !== null) {
					$lookup[$opt->alias] = $opt;
				}
			}
		}

		$params = array_fill_keys($names, null);
		$i = 0;
		while ($i < count($args)) {
			$arg = $args[$i++];
			if ($arg[0] !== '-') {
				continue;
			}

			[$name, $value] = strpos($arg, '=') ? explode('=', $arg, 2) : [$arg, self::OptionPresent];
			$opt = $lookup[$name] ?? null;
			if (!$opt) {
				continue;
			}

			if ($value === self::OptionPresent && $opt->type !== ValueType::None) {
				if (isset($args[$i]) && $args[$i][0] !== '-') {
					$value = $args[$i++];
				}
			}

			$params[$opt->name] = $value;
		}

		return $params;
	}


	/**
	 * Prints help text to stdout.
	 */
	public function help(): void
	{
		echo $this->help;
	}


	private function normalizeValue(Option $opt, mixed $value): mixed
	{
		if ($opt->enum && $value !== self::OptionPresent && !in_array($value, $opt->enum, strict: true)) {
			throw new \Exception("Value of option $opt->name must be " . implode(', or ', $opt->enum) . '.');
		}

		return $opt->normalizer ? ($opt->normalizer)($value) : $value;
	}


	/**
	 * Normalizer that resolves path to absolute and validates existence.
	 */
	public static function normalizeRealPath(string $value): string
	{
		$path = realpath($value);
		if ($path === false) {
			throw new \Exception("File path '$value' not found.");
		}

		return $path;
	}


	/**
	 * Returns true if no command-line arguments were provided.
	 */
	public function isEmpty(): bool
	{
		return !$this->args;
	}
}
