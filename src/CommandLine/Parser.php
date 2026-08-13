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
use Nette\CommandLine\Parameters\ValueParameter;
use function array_key_exists, count, is_array, strlen;


/**
 * Reads a command line according to the definition of a command.
 */
final class Parser
{
	private const OptionPresent = true;


	/**
	 * Parses the command line, which is the one of the running process unless given. The line is always read from
	 * the root; given a subcommand, it has to run that command or one below it.
	 * @param  ?list<string>  $args  the tokens without the name of the program
	 */
	public function parse(Command $command, ?array $args = null): Result
	{
		$args ??= array_values(array_map(strval(...), array_slice((array) ($_SERVER['argv'] ?? []), 1)));

		[$selected, $occurrences] = $this->collect($command->getRoot(), $args);
		if (!in_array($command, $selected->getPath(), true)) {
			throw new ParseException("The command line does not run command '{$command->getFullName()}'.", $selected, reason: ParseError::CommandMismatch);
		}

		$parameters = self::listParameters($selected);
		$standalone = self::findStandalone($parameters, $occurrences);
		if (!$standalone && $selected->commandRequired && $selected->getCommands()) {
			throw new ParseException('Missing command.', $selected, reason: ParseError::MissingCommand);
		}

		$values = $standalone
			// it answers on its own: nothing else is checked, converted or demanded
			? $this->complete($parameters, $this->evaluate($parameters, $standalone, $selected), demandArguments: false)
			: $this->complete($parameters, $this->evaluate($parameters, $occurrences, $selected));

		return new Result($selected, $values, array_keys($occurrences), $args === []);
	}


	/**
	 * @return array<string, Parameter>  the parameters of the command and of all commands above it
	 */
	private static function listParameters(Command $command): array
	{
		$parameters = [];
		foreach ($command->getPath() as $level) {
			foreach ($level->getParameters() as $parameter) {
				$parameters[$parameter->name] = $parameter;
			}
		}

		return $parameters;
	}


	/**
	 * @param  array<string, Parameter>  $parameters
	 * @param  array<string, list<mixed>>  $occurrences
	 * @return array<string, list<mixed>>  the occurrences of the standalone flags that were used
	 */
	private static function findStandalone(array $parameters, array $occurrences): array
	{
		return array_filter(
			$occurrences,
			fn(array $supplied, string $name) => $parameters[$name] instanceof Flag && $parameters[$name]->standalone && end($supplied) === true,
			ARRAY_FILTER_USE_BOTH,
		);
	}


	/**
	 * Phase 1: reads the tokens into raw occurrences per parameter and follows the command names down the tree,
	 * resolving aliases and bundles. An option used without a value yields the OptionPresent sentinel.
	 * @param  list<string>  $args
	 * @return array{Command, array<string, list<mixed>>}  the command the line runs and the occurrences
	 */
	private function collect(Command $root, array $args): array
	{
		$command = $root;
		[$names, $negations] = self::indexOptions($command);
		$arguments = $command->getArguments();
		$occurrences = $extra = [];
		$onlyPositional = false;

		$i = 0;
		while ($i < count($args)) {
			$arg = $args[$i++];
			if (!$onlyPositional && $arg === '--') { // everything after -- is positional
				$onlyPositional = true;
				continue;

			} elseif (
				!$onlyPositional
				&& !self::isOption($arg, $names)
				&& ($commands = $command->getCommands())
			) {
				$subcommand = array_values(array_filter($commands, fn(Command $candidate) => $candidate->name === $arg))[0] ?? null;
				if (!$subcommand) {
					$hint = self::suggest($arg, array_map(fn(Command $candidate) => (string) $candidate->name, $commands));
					throw new ParseException("Unknown command '" . self::escape($arg) . "'." . ($hint === null ? '' : " Did you mean '$hint'?"), $command, reason: ParseError::UnknownCommand);
				}

				$command = $subcommand;
				[$names, $negations] = self::indexOptions($command);
				$arguments = $command->getArguments();
				continue;

			} elseif ($onlyPositional || !self::isOption($arg, $names)) {
				$argument = current($arguments);
				if (!$argument) {
					$extra[] = $arg;
				} else {
					$occurrences[$argument->name][] = $arg;
					if (!$argument->repeatable) { // a repeatable argument consumes all remaining values
						next($arguments);
					}
				}

				continue;
			}

			[$name, $value] = self::splitNameValue($arg);
			if (isset($negations[$name])) {
				if ($value !== self::OptionPresent) {
					throw new ParseException("Option $name does not accept a value.", $command, reason: ParseError::UnexpectedValue, parameter: $negations[$name]);
				}

				$occurrences[$negations[$name]->name][] = false;
				continue;
			}

			$option = $names[$name] ?? null;
			if (!$option) {
				if ($value === self::OptionPresent && ($bundle = self::expandBundle($name, $names)) !== null) {
					array_splice($args, $i, 0, $bundle); // replace -abc with -a -b -c and reprocess
					continue;
				}

				throw new ParseException(
					self::describeUnknownOption($name, $command, [...array_keys($names), ...array_keys($negations)]),
					$command,
					reason: ParseError::UnknownOption,
				);
			}

			if ($value !== self::OptionPresent && $option instanceof Flag) {
				throw new ParseException("Option $option->name does not accept a value.", $command, reason: ParseError::UnexpectedValue, parameter: $option);

			} elseif ($value === self::OptionPresent && $option instanceof Option) {
				$next = isset($args[$i]) && !self::isOption($args[$i], $names) ? $args[$i] : null;
				if (!$option->valueOptional) {
					$value = $next ?? throw new ParseException("Option $option->name requires a value.", $command, reason: ParseError::MissingValue, parameter: $option);
					$i++;

				} elseif ($next !== null && in_array($next, $option->enum ?? [], true)) {
					// an optional value is attached with =, unless the next token is one of the enum values
					$value = $args[$i++];
				}
			}

			$occurrences[$option->name][] = $value;
		}

		if ($extra) {
			$list = implode(', ', array_map(self::escape(...), $extra));
			throw new ParseException(
				count($extra) === 1 ? "Unexpected argument $list." : "Unexpected arguments $list.",
				$command,
				reason: ParseError::UnexpectedArgument,
			);
		}

		return [$command, $occurrences];
	}


	/**
	 * Phase 2: checks and converts the values that were actually supplied. Only the last value of a parameter that
	 * is not repeatable counts, so the earlier ones are neither checked nor converted.
	 * @param  array<string, Parameter>  $parameters
	 * @param  array<string, list<mixed>>  $occurrences
	 * @return array<string, mixed>
	 */
	private function evaluate(array $parameters, array $occurrences, Command $command): array
	{
		$values = [];
		foreach ($occurrences as $name => $supplied) {
			$parameter = $parameters[$name];
			$supplied = $parameter->repeatable ? $supplied : [end($supplied)];
			if ($parameter instanceof ValueParameter) {
				$supplied = array_map(fn($value) => $this->normalizeValue($parameter, $value, $command), $supplied);
			}

			$values[$name] = $parameter->repeatable ? $supplied : $supplied[0];
		}

		return $values;
	}


	/**
	 * Phase 3: fills in what did not occur. Presence is decided by the occurrences, not by the
	 * value, so a normalizer may return null without the default value overwriting it.
	 * @param  array<string, Parameter>  $parameters
	 * @param  array<string, mixed>  $values
	 * @param  bool  $demandArguments  false when a standalone flag answered instead
	 * @return array<string, mixed>
	 */
	private function complete(array $parameters, array $values, bool $demandArguments = true): array
	{
		foreach ($parameters as $name => $parameter) {
			if (array_key_exists($name, $values)) {
				continue;
			} elseif ($demandArguments && $parameter instanceof Argument && !$parameter->optional) {
				throw new ParseException("Missing required argument <$name>.", $parameter->command, reason: ParseError::MissingArgument, parameter: $parameter);
			}

			$default = $parameter->default;
			$values[$name] = $parameter->repeatable && !is_array($default)
				? ($default === null ? [] : [$default])
				: $default;
		}

		return $values;
	}


	/**
	 * Indexes the options valid at the command by name and by alias, and apart from them by the negation of a flag.
	 * @return array{array<string, Flag|Option>, array<string, Flag>}
	 */
	private static function indexOptions(Command $command): array
	{
		$names = $negations = [];
		foreach ($command->getPath() as $level) {
			foreach ($level->getOptions() as $option) {
				$names[$option->name] = $option;
				if ($option->alias !== null) {
					$names[$option->alias] = $option;
				}

				if ($option instanceof Flag && $option->negation !== null) {
					$negations[$option->negation] = $option;
				}
			}
		}

		return [$names, $negations];
	}


	/**
	 * @param  list<string>  $known  names and aliases valid at the command
	 */
	private static function describeUnknownOption(string $name, Command $command, array $known): string
	{
		$owner = self::findOwner($command->getRoot(), $name);
		if ($owner !== null) {
			return "Option $name belongs to command '{$owner->getFullName()}'.";
		}

		$hint = self::suggest($name, $known);
		return 'Unknown option ' . self::escape($name) . '.' . ($hint === null ? '' : " Did you mean $hint?");
	}


	/**
	 * Finds the command in the tree that defines an option by this name or alias.
	 */
	private static function findOwner(Command $command, string $name): ?Command
	{
		foreach ($command->getOptions() as $option) {
			if ($option->hasName($name)) {
				return $command;
			}
		}

		foreach ($command->getCommands() as $subcommand) {
			if ($owner = self::findOwner($subcommand, $name)) {
				return $owner;
			}
		}

		return null;
	}


	/**
	 * Finds the candidate closest to an unknown name, or null when nothing is similar enough.
	 * @param  list<string>  $candidates
	 */
	private static function suggest(string $unknown, array $candidates): ?string
	{
		$best = null;
		$bestDist = PHP_INT_MAX;
		foreach ($candidates as $candidate) {
			if (($dist = levenshtein($unknown, $candidate)) < $bestDist) {
				$bestDist = $dist;
				$best = $candidate;
			}
		}

		// require a close match that is not just "any other short flag"
		return $bestDist <= 2 && strlen($unknown) - $bestDist >= 2 ? $best : null;
	}


	/**
	 * Expands a bundle of short flags like -abc into ['-a', '-b', '-c'], or returns null when it is not a clean
	 * bundle. Every character must be a defined single-letter flag; only the last one may take a value.
	 * @param  array<string, Flag|Option>  $names
	 * @return ?list<string>
	 */
	private static function expandBundle(string $token, array $names): ?array
	{
		if (!preg_match('#^-\w{2,}$#D', $token)) {
			return null;
		}

		$chars = str_split(substr($token, 1));
		$bundle = [];
		foreach ($chars as $j => $char) {
			$short = "-$char";
			$option = $names[$short] ?? null;
			$isLast = $j === count($chars) - 1;
			if ($option === null || (!$isLast && !$option instanceof Flag)) {
				return null;
			}

			$bundle[] = $short;
		}

		return $bundle;
	}


	private function normalizeValue(ValueParameter $parameter, mixed $value, Command $command): mixed
	{
		if ($value === self::OptionPresent) {
			// option used without a value (optional value) - keep the `true` sentinel untouched
			return true;
		}

		// a bad value is reported by throwing; an \Error means the normalizer itself is broken
		try {
			return $parameter->normalize($value);
		} catch (ParseException $e) {
			throw $e;
		} catch (\Exception $e) { // dresscode:ignore reference-throwable-only
			$label = $parameter instanceof Option ? "Option $parameter->name" : "Argument <$parameter->name>";
			throw new ParseException("$label: " . self::escape($e->getMessage()), $command, $e, ParseError::InvalidValue, $parameter);
		}
	}


	/**
	 * Returns true if the token looks like an option, not a positional value. A lone "-" is a value by convention
	 * (usually meaning stdin/stdout), and so is a negative number, unless an option by that name is defined.
	 * @param  array<string, Flag|Option>  $names
	 */
	private static function isOption(string $arg, array $names): bool
	{
		if ($arg === '' || $arg === '-' || $arg[0] !== '-') {
			return false;
		}

		$name = self::splitNameValue($arg)[0];
		return !preg_match('#^-\d#', $arg) || isset($names[$name]);
	}


	/**
	 * Splits a "--name=value" token into [name, value]; a bare "--name" yields the OptionPresent sentinel as value.
	 * @return array{string, string|true}
	 */
	private static function splitNameValue(string $arg): array
	{
		return ($eq = strpos($arg, '=')) !== false
			? [substr($arg, 0, $eq), substr($arg, $eq + 1)]
			: [$arg, self::OptionPresent];
	}


	/**
	 * Makes a text from the command line safe to show in a message, with control characters as escape sequences.
	 */
	private static function escape(string $s): string
	{
		return addcslashes($s, "\0..\37\177");
	}
}
