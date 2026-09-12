<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Parameters\{Argument, Flag, Option, Parameter, ValueParameter};
use function count, is_bool, is_int;


/**
 * Binds the parameters of a handler to the inputs of its command, checks that they accept every value the inputs
 * can give, and calls it.
 * @internal
 */
final class HandlerBinding
{
	/**
	 * Refuses what the signature of a handler says wrong on its own, before any command line is read.
	 */
	public static function checkSignature(\Closure $handler): void
	{
		$function = new \ReflectionFunction($handler);
		self::checkParameters($function->getParameters(), 'handler ' . self::describeHandler($function));
		$type = $function->getReturnType();
		$returns = $type === null ? null : self::listTypes($type);
		if ($type !== null && ($returns === null || array_diff($returns, ['int', 'null', 'void', 'never', 'mixed']))) {
			throw new \InvalidArgumentException('Handler ' . self::describeHandler($function) . " returns $type; it returns an exit code, int or nothing.");
		}
	}


	/**
	 * Checks that every handler in the tree binds to the inputs of its command and accepts their values.
	 */
	public static function checkTree(Command $command): void
	{
		if ($command->handler !== null) {
			self::bind($command, $command->handler);
		}

		foreach ($command->getCommands() as $subcommand) {
			self::checkTree($subcommand);
		}
	}


	/**
	 * Calls the handler of the command the line runs with the values bound to its parameters.
	 */
	public static function invoke(ParseResult $result): int
	{
		$handler = $result->command->handler ?? throw new \LogicException($result->command->getLabel() . ' has no handler to run.');
		$code = $handler(...array_map(fn(\Closure $resolve) => $resolve($result), self::bind($result->command, $handler)));
		return match (true) {
			is_int($code) => $code,
			$code === null => 0,
			default => throw new \LogicException(
				'Handler ' . self::describeHandler(new \ReflectionFunction($handler))
				. ' returned ' . self::describeValue($code)
				. '; it returns an exit code, int or nothing.',
			),
		};
	}


	/**
	 * @param  list<\ReflectionParameter>  $parameters
	 * @param  string  $owner  the handler the parameters belong to, for the messages
	 */
	private static function checkParameters(array $parameters, string $owner): void
	{
		foreach ($parameters as $parameter) {
			$label = 'Parameter $' . $parameter->name . ' of ' . $owner;
			if ($parameter->isVariadic()) {
				throw new \InvalidArgumentException("$label is variadic; take a repeatable input as an array.");
			} elseif ($parameter->isPassedByReference()) {
				throw new \InvalidArgumentException("$label is passed by reference.");
			} elseif ($parameter->isDefaultValueAvailable()) {
				throw new \InvalidArgumentException("$label has a default value, which would never be used: it binds to an input defined elsewhere, which carries the default.");
			}
		}
	}


	/**
	 * Finds what each parameter is given, as a function of the result: the result, the command or an input on the way
	 * to it.
	 * @return list<\Closure(ParseResult): mixed>
	 */
	private static function bind(Command $command, \Closure $handler): array
	{
		$function = new \ReflectionFunction($handler);
		return self::bindParameters($command, $function->getParameters(), 'handler ' . self::describeHandler($function));
	}


	/**
	 * @param  list<\ReflectionParameter>  $parameters
	 * @return list<\Closure(ParseResult): mixed>
	 */
	private static function bindParameters(Command $command, array $parameters, string $owner): array
	{
		$resolvers = [];
		foreach ($parameters as $parameter) {
			$injection = self::findInjection($parameter);
			if ($injection === ParseResult::class) {
				$resolvers[] = fn(ParseResult $result) => $result;
			} elseif ($injection === Command::class) {
				$resolvers[] = fn(ParseResult $result) => $result->command;
			} else {
				$input = self::findInput($command, $owner, $parameter);
				$resolvers[] = fn(ParseResult $result) => $result->get($input->name);
			}
		}

		return $resolvers;
	}


	/**
	 * @return class-string<ParseResult|Command>|null
	 */
	private static function findInjection(\ReflectionParameter $parameter): ?string
	{
		$type = $parameter->getType();
		$name = $type instanceof \ReflectionNamedType ? $type->getName() : null;
		return $name === ParseResult::class || $name === Command::class ? $name : null;
	}


	/**
	 * Finds the input the parameter is named after, the option --max-warnings or the argument max-warnings for
	 * $maxWarnings, and checks it gives nothing the parameter refuses.
	 */
	private static function findInput(Command $command, string $owner, \ReflectionParameter $parameter): Parameter
	{
		$label = $command->getLabel() . ': parameter $' . $parameter->name . ' of ' . $owner;
		$name = self::deriveName($parameter->name);
		$found = [];
		foreach ($command->getPath() as $level) {
			foreach ($level->getParameters() as $input) {
				if ($input->name === "--$name" || ($input instanceof Argument && $input->name === $name)) {
					$found[] = $input;
				}
			}
		}

		if (!$found) {
			throw new \InvalidArgumentException("$label has no input; define option --$name or argument <$name>, or read it from ParseResult.");
		} elseif (count($found) > 1) {
			throw new \InvalidArgumentException("$label matches both option --$name and argument <$name>; read them from ParseResult.");
		}

		self::checkType($parameter, $found[0], $label);
		return $found[0];
	}


	private static function checkType(\ReflectionParameter $parameter, Parameter $input, string $label): void
	{
		$type = $parameter->getType();
		$accepted = $type === null ? ['mixed'] : self::listTypes($type);
		if ($accepted === null) {
			throw new \InvalidArgumentException("$label has type $type, which cannot be checked; use a named type or a union of them.");
		} elseif (in_array('mixed', $accepted, true)) {
			return;
		}

		$what = $input instanceof Argument ? "argument <$input->name>" : "option $input->name";
		foreach (self::describeValues($input) as [$value, $when]) {
			if ($value === null) {
				throw new \InvalidArgumentException("$label cannot be checked, since the normalizer of $what declares no return type.");
			} elseif (!self::accepts($accepted, $value)) {
				throw new \InvalidArgumentException("$label does not accept $value, which $what gives $when.");
			}
		}
	}


	/**
	 * Lists the types of the values an input can give, with when it gives them; null stands for a type the
	 * normalizer does not declare.
	 * @return list<array{?string, string}>
	 */
	private static function describeValues(Parameter $input): array
	{
		if ($input->repeatable) {
			return [['array', 'as the list of its values']];
		}

		$values = [];
		if ($input instanceof Flag) {
			$values[] = ['true', 'when used'];
			if ($input->negatable) {
				$values[] = ['false', 'when negated'];
			}

		} elseif ($input instanceof ValueParameter) {
			$returned = $input->normalizer === null ? null : (new \ReflectionFunction($input->normalizer))->getReturnType();
			$types = match (true) {
				$input->normalizer === null => [$input->enumClass ?? 'string'],
				$returned === null => [null],
				default => self::listTypes($returned) ?? [null],
			};
			foreach ($types as $type) {
				if ($type !== 'never') {
					$values[] = [$type === 'void' ? 'null' : $type, $input->normalizer === null ? 'as its value' : 'from its normalizer'];
				}
			}

			if ($input instanceof Option && $input->valueOptional) {
				$values[] = ['true', 'when used without a value'];
			}
		}

		if (!$input instanceof Argument || $input->optional) {
			$values[] = [self::describeValue($input->default), 'when absent'];
		}

		return $values;
	}


	/**
	 * The names of the types a declaration allows, null included; null when it holds an intersection.
	 * @return ?list<string>
	 */
	private static function listTypes(\ReflectionType $type): ?array
	{
		$types = [];
		foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $member) {
			if (!$member instanceof \ReflectionNamedType) {
				return null;
			}

			$types[] = $member->getName();
		}

		if ($type->allowsNull() && !in_array('null', $types, true) && !in_array('mixed', $types, true)) {
			$types[] = 'null';
		}

		return $types;
	}


	/**
	 * Tells whether a declaration listed by listTypes() accepts a value of the type, the way a strict call does.
	 * @param  list<string>  $accepted
	 */
	private static function accepts(array $accepted, string $type): bool
	{
		if (in_array('mixed', $accepted, true) || in_array($type, $accepted, true)) {
			return true;
		}

		return match ($type) {
			'true', 'false' => in_array('bool', $accepted, true),
			'bool' => in_array('true', $accepted, true) && in_array('false', $accepted, true),
			'int' => in_array('float', $accepted, true),
			'array' => in_array('iterable', $accepted, true),
			'mixed', 'null', 'float', 'string', 'iterable', 'callable', 'object' => false,
			default => (bool) array_filter($accepted, fn(string $declared) => match ($declared) {
				'object' => true,
				'iterable' => is_a($type, \Traversable::class, allow_string: true),
				'callable' => method_exists($type, '__invoke'),
				default => is_a($type, $declared, allow_string: true),
			}),
		};
	}


	private static function describeValue(mixed $value): string
	{
		return is_bool($value) ? ($value ? 'true' : 'false') : get_debug_type($value);
	}


	/**
	 * The name of an input the parameter is named after: $maxWarnings is max-warnings.
	 */
	private static function deriveName(string $parameter): string
	{
		return strtolower((string) preg_replace('#(?<=[a-z0-9])(?=[A-Z])#', '-', $parameter));
	}


	private static function describeHandler(\ReflectionFunction $function): string
	{
		if (str_starts_with($function->name, '{closure')) {
			return 'closure in ' . basename((string) $function->getFileName()) . ':' . $function->getStartLine();
		}

		$class = $function->getClosureScopeClass();
		return ($class === null ? '' : $class->name . '::') . $function->name . '()';
	}
}
