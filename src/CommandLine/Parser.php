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

	/** @var array<string, Option> */
	private array $options = [];

	/** @var string[] */
	private array $aliases = [];

	/** @var string[] */
	private array $positional = [];

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
			if ($name !== $m[1][0]) {
				$this->aliases[$m[1][0]] = $name;
			}
		}

		foreach ($defaults as $name => $opt) {
			$default = $opt[self::Default] ?? null;
			$this->options[$name] = new Option(
				name: $name,
				type: match (true) {
					!($opt[self::Argument] ?? true) => ValueType::None,
					($opt[self::Optional] ?? false) || $default !== null => ValueType::Optional,
					default => ValueType::Required,
				},
				repeatable: (bool) ($opt[self::Repeatable] ?? null),
				fallback: $default,
				normalizer: $opt[self::Normalizer] ?? null,
				realpath: (bool) ($opt[self::RealPath] ?? false),
				enum: $opt[self::Enum] ?? null,
			);
			if ($this->options[$name]->positional) {
				$this->positional[] = $name;
			}
		}

		$this->help .= $help;
		return $this;
	}


	/**
	 * Parses command-line arguments and returns associative array of values.
	 * @param array|null $args  Arguments to parse (defaults to $_SERVER['argv'])
	 */
	public function parse(?array $args = null): array
	{
		$args ??= $this->args;

		$params = [];
		reset($this->positional);
		$i = 0;
		while ($i < count($args)) {
			$arg = $args[$i++];
			if ($arg[0] !== '-') {
				if (!current($this->positional)) {
					throw new \Exception("Unexpected parameter $arg.");
				}

				$name = current($this->positional);
				$opt = $this->options[$name];
				$this->checkArg($opt, $arg);
				if (!$opt->repeatable) {
					$params[$name] = $arg;
					next($this->positional);
				} else {
					$params[$name][] = $arg;
				}

				continue;
			}

			[$name, $arg] = strpos($arg, '=') ? explode('=', $arg, 2) : [$arg, true];

			if (isset($this->aliases[$name])) {
				$name = $this->aliases[$name];

			} elseif (!isset($this->options[$name])) {
				throw new \Exception("Unknown option $name.");
			}

			$opt = $this->options[$name];

			if ($arg !== true && $opt->type === ValueType::None) {
				throw new \Exception("Option $name has not argument.");

			} elseif ($arg === true && $opt->type !== ValueType::None) {
				if (isset($args[$i]) && $args[$i][0] !== '-') {
					$arg = $args[$i++];
				} elseif ($opt->type === ValueType::Required) {
					throw new \Exception("Option $name requires argument.");
				}
			}

			if (
				$opt->enum
				&& !in_array($arg, $opt->enum, true)
				&& !($opt->type === ValueType::Optional && $arg === true)
			) {
				throw new \Exception("Value of option $name must be " . implode(', or ', $opt->enum) . '.');
			}

			$this->checkArg($opt, $arg);

			if (!$opt->repeatable) {
				$params[$name] = $arg;
			} else {
				$params[$name][] = $arg;
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
	 * Prints help text to stdout.
	 */
	public function help(): void
	{
		echo $this->help;
	}


	public function checkArg(Option $opt, mixed &$arg): void
	{
		if ($opt->normalizer) {
			$arg = ($opt->normalizer)($arg);
		}

		if ($opt->realpath) {
			$path = realpath($arg);
			if ($path === false) {
				throw new \Exception("File path '$arg' not found.");
			}

			$arg = $path;
		}
	}


	/**
	 * Returns true if no command-line arguments were provided.
	 */
	public function isEmpty(): bool
	{
		return !$this->args;
	}
}
