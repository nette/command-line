<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Parameters\{Argument, Flag, Option, Parameter, ValueParameter};
use function count, is_array, is_bool, is_int, is_object;


/**
 * Defines the inputs the attributes of a handler declare, binds its parameters to the inputs of its command, checks
 * that they accept every value the inputs can give, and calls it. A parameter typed by a class whose constructor
 * declares inputs is an object of options: its inputs are defined and bound the same way, and it is built for the call.
 * @internal
 */
final class HandlerBinding
{
	private const InputAttributes = [Attributes\Flag::class, Attributes\Option::class, Attributes\Argument::class];


	/**
	 * Finds the attribute #[Command] of the handler, refusing an attribute of an input placed on it.
	 */
	public static function findCommand(\Closure $handler): ?Attributes\Command
	{
		$function = new \ReflectionFunction($handler);
		foreach ($function->getAttributes() as $attribute) {
			if (in_array($attribute->getName(), self::InputAttributes, true)) {
				throw new \InvalidArgumentException(
					'Attribute #[' . self::shortenName($attribute->getName()) . '] of handler '
					. self::describeHandler($function)
					. ' belongs to a parameter.',
				);
			}
		}

		return ($function->getAttributes(Attributes\Command::class)[0] ?? null)?->newInstance();
	}


	/**
	 * The name of the command a method runs, migrate-suppressions for migrateSuppressions(); null for a closure and
	 * for __invoke(), whose name says nothing of the command.
	 */
	public static function deriveCommandName(\Closure $handler): ?string
	{
		$function = new \ReflectionFunction($handler);
		return str_starts_with($function->name, '{closure') || str_starts_with($function->name, '__')
			? null
			: self::deriveName($function->name);
	}


	/**
	 * Refuses what the signature of a handler says wrong on its own, before any command line is read.
	 * @param  bool  $described  the handler describes its command by attributes, which are otherwise refused
	 */
	public static function checkSignature(\Closure $handler, bool $described): void
	{
		$function = new \ReflectionFunction($handler);
		$attributes = $function->getAttributes();
		foreach ($function->getParameters() as $parameter) {
			$attributes = [...$attributes, ...$parameter->getAttributes()];
		}

		foreach ($described ? [] : $attributes as $attribute) {
			if (in_array($attribute->getName(), [Attributes\Command::class, ...self::InputAttributes], true)) {
				throw new \InvalidArgumentException(
					'Attribute #[' . self::shortenName($attribute->getName()) . '] of handler '
					. self::describeHandler($function)
					. ' is only read by addCommandFrom().',
				);
			}
		}

		foreach ($described ? [] : $function->getParameters() as $parameter) {
			if ($class = self::findOptionsClass($parameter)) {
				throw new \InvalidArgumentException('Parameter $' . $parameter->name . ' of handler ' . self::describeHandler($function) . " is an object of options $class->name, which is only read by addCommandFrom().");
			}
		}

		self::checkParameters($function->getParameters(), 'handler ' . self::describeHandler($function), []);
		$type = $function->getReturnType();
		$returns = $type === null ? null : self::listTypes($type);
		if ($type !== null && ($returns === null || array_diff($returns, ['int', 'null', 'void', 'never', 'mixed']))) {
			throw new \InvalidArgumentException('Handler ' . self::describeHandler($function) . " returns $type; it returns an exit code, int or nothing.");
		}
	}


	/**
	 * Adds to the command the inputs its handler declares by attributes of its parameters and of the constructors of
	 * its objects of options, in their order, and checks the parameters accept their values.
	 */
	public static function addParameters(Command $command, \Closure $handler): void
	{
		$function = new \ReflectionFunction($handler);
		self::addInputs($command, $function->getParameters(), 'handler ' . self::describeHandler($function));
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
	 * @param  string  $owner  the handler or the constructor the parameters belong to, for the messages
	 * @param  list<string>  $classes  the objects of options the parameters are nested in, to refuse a cycle
	 */
	private static function checkParameters(array $parameters, string $owner, array $classes): void
	{
		foreach ($parameters as $parameter) {
			$label = 'Parameter $' . $parameter->name . ' of ' . $owner;
			$attribute = self::findInputAttribute($parameter, $label);
			$default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
			if ($parameter->getAttributes(Attributes\Command::class)) {
				throw new \InvalidArgumentException("$label has attribute #[Command], which belongs to the handler.");
			} elseif ($parameter->isVariadic()) {
				throw new \InvalidArgumentException("$label is variadic; take a repeatable input as an array.");
			} elseif ($parameter->isPassedByReference()) {
				throw new \InvalidArgumentException("$label is passed by reference.");
			} elseif ($attribute !== null && self::findInjection($parameter) !== null) {
				throw new \InvalidArgumentException("$label is given by its type, so it defines no input.");
			} elseif ($attribute === null && $parameter->isDefaultValueAvailable()) {
				throw new \InvalidArgumentException("$label has a default value, which would never be used: without an attribute it binds to an input defined elsewhere, which carries the default.");
			} elseif (is_object($default) && !$default instanceof \UnitEnum) {
				throw new \InvalidArgumentException("$label has an object as its default value, which every run would share.");
			} elseif (
				$attribute instanceof Attributes\Argument
				&& $attribute->optional === false
				&& $parameter->isDefaultValueAvailable()
			) {
				throw new \InvalidArgumentException("$label defines a required argument, so its default value would never be used.");
			} elseif ($attribute === null && ($class = self::findOptionsClass($parameter))) {
				if (!$class->isInstantiable()) {
					throw new \InvalidArgumentException("$label is an object of options $class->name, which cannot be created; it needs a public constructor and no abstract class.");
				} elseif (in_array($class->name, $classes, true)) {
					throw new \InvalidArgumentException("$label is an object of options $class->name, which contains itself: " . implode(' > ', [...$classes, $class->name]) . '.');
				}

				self::checkParameters(
					$class->getConstructor()?->getParameters() ?? [],
					$class->name . '::__construct()',
					[...$classes, $class->name],
				);
			}
		}
	}


	/**
	 * @param  list<\ReflectionParameter>  $parameters
	 */
	private static function addInputs(Command $command, array $parameters, string $owner): void
	{
		foreach ($parameters as $parameter) {
			$label = 'Parameter $' . $parameter->name . ' of ' . $owner;
			$attribute = self::findInputAttribute($parameter, $label);
			if ($attribute === null) {
				if ($class = self::findOptionsClass($parameter)) {
					self::addInputs($command, $class->getConstructor()?->getParameters() ?? [], $class->name . '::__construct()');
				}

				continue;
			}

			$name = self::deriveInputName($parameter, $attribute);
			$default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
			$normalizer = $attribute instanceof Attributes\Flag ? null : self::createNormalizer($attribute->normalizer, $label);
			$input = match (true) {
				$attribute instanceof Attributes\Flag => $command->addFlag(
					$name,
					$attribute->description,
					alias: $attribute->alias,
					standalone: $attribute->standalone,
					negatable: $attribute->negatable,
					default: $default,
					defaultDescription: $attribute->defaultDescription,
					repeatable: $attribute->repeatable,
					hidden: $attribute->hidden,
				),
				$attribute instanceof Attributes\Option => $command->addOption(
					$name,
					$attribute->description,
					alias: $attribute->alias,
					valueName: $attribute->valueName,
					valueOptional: $attribute->valueOptional,
					enum: $attribute->enum ?? ($normalizer === null ? self::findEnumClass($parameter) : null),
					normalizer: $normalizer,
					default: $default,
					defaultDescription: $attribute->defaultDescription,
					repeatable: $attribute->repeatable,
					hidden: $attribute->hidden,
				),
				default => $command->addArgument(
					$name,
					$attribute->description,
					optional: $attribute->optional ?? $parameter->isDefaultValueAvailable(),
					enum: $attribute->enum ?? ($normalizer === null ? self::findEnumClass($parameter) : null),
					normalizer: $normalizer,
					default: $default,
					defaultDescription: $attribute->defaultDescription,
					repeatable: $attribute->repeatable,
					hidden: $attribute->hidden,
				),
			};
			self::checkType($parameter, $input, $label);
		}
	}


	/**
	 * A normalizer written in an attribute: a closure, or a static method as [class, name], which every PHP writes there.
	 * @param  \Closure|array{class-string, string}|null  $normalizer
	 */
	private static function createNormalizer(\Closure|array|null $normalizer, string $label): ?\Closure
	{
		if (!is_array($normalizer)) {
			return $normalizer;
		}

		[$class, $method] = $normalizer;
		$reflection = method_exists($class, $method) ? new \ReflectionMethod($class, $method) : null;
		return $reflection?->isStatic()
			? $reflection->getClosure()
			: throw new \InvalidArgumentException("$label has normalizer $class::$method(), which is no static method.");
	}


	/**
	 * Finds what each parameter is given, as a function of the result: the result, the command, an input on the way to
	 * it, or an object of options created from what its constructor is given.
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
			$attribute = self::findInputAttribute($parameter, 'Parameter $' . $parameter->name . ' of ' . $owner);
			$class = $attribute === null ? self::findOptionsClass($parameter) : null;
			$injection = self::findInjection($parameter);
			if ($injection === ParseResult::class) {
				$resolvers[] = fn(ParseResult $result) => $result;
			} elseif ($injection === Command::class) {
				$resolvers[] = fn(ParseResult $result) => $result->command;
			} elseif ($class !== null) {
				$inner = self::bindParameters($command, $class->getConstructor()?->getParameters() ?? [], $class->name . '::__construct()');
				$resolvers[] = fn(ParseResult $result) => $class->newInstance(
					...array_map(fn(\Closure $resolve) => $resolve($result), $inner),
				);
			} else {
				$input = self::findInput($command, $owner, $parameter, $attribute);
				$resolvers[] = fn(ParseResult $result) => $result[$input->name];
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
	 * Finds the class of an object of options, which the parameter is typed by and whose constructor declares inputs.
	 * @return ?\ReflectionClass<object>
	 */
	private static function findOptionsClass(\ReflectionParameter $parameter): ?\ReflectionClass
	{
		$type = $parameter->getType();
		if (
			!$type instanceof \ReflectionNamedType
			|| $type->isBuiltin()
			|| self::findInjection($parameter) !== null
			|| !class_exists($type->getName())
		) {
			return null;
		}

		$class = new \ReflectionClass($type->getName());
		foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
			foreach ($parameter->getAttributes() as $attribute) {
				if (in_array($attribute->getName(), self::InputAttributes, true)) {
					return $class;
				}
			}
		}

		return null;
	}


	/**
	 * Finds the input the parameter defines by its attribute, or the one it is named after, the option --max-warnings
	 * or the argument max-warnings for $maxWarnings, and checks it gives nothing the parameter refuses.
	 */
	private static function findInput(
		Command $command,
		string $owner,
		\ReflectionParameter $parameter,
		Attributes\Flag|Attributes\Option|Attributes\Argument|null $attribute,
	): Parameter
	{
		$label = $command->getLabel() . ': parameter $' . $parameter->name . ' of ' . $owner;
		$name = self::deriveName($parameter->name);
		$found = [];
		foreach ($attribute === null ? $command->getPath() : [$command] as $level) {
			foreach ($level->getParameters() as $input) {
				if ($attribute === null
					? $input->name === "--$name" || ($input instanceof Argument && $input->name === $name)
					: $input->name === self::deriveInputName($parameter, $attribute)
				) {
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


	/**
	 * Finds the attribute #[Flag], #[Option] or #[Argument] by which the parameter defines its input.
	 */
	private static function findInputAttribute(
		\ReflectionParameter $parameter,
		string $label,
	): Attributes\Flag|Attributes\Option|Attributes\Argument|null
	{
		$attributes = array_filter(
			$parameter->getAttributes(),
			fn(\ReflectionAttribute $attribute) => in_array($attribute->getName(), self::InputAttributes, true),
		);
		if (count($attributes) > 1) {
			throw new \InvalidArgumentException("$label has more than one of the attributes #[Flag], #[Option] and #[Argument].");
		}

		$attribute = $attributes ? reset($attributes)->newInstance() : null;
		return $attribute instanceof Attributes\Flag || $attribute instanceof Attributes\Option || $attribute instanceof Attributes\Argument
			? $attribute
			: null;
	}


	/**
	 * The name of the input an attribute defines: its own, or the one derived from the parameter, --dry-run for $dryRun.
	 */
	private static function deriveInputName(
		\ReflectionParameter $parameter,
		Attributes\Flag|Attributes\Option|Attributes\Argument $attribute,
	): string
	{
		return $attribute->name
			?? ($attribute instanceof Attributes\Argument ? '' : '--') . self::deriveName($parameter->name);
	}


	/**
	 * @return ?class-string<\BackedEnum>
	 */
	private static function findEnumClass(\ReflectionParameter $parameter): ?string
	{
		$type = $parameter->getType();
		$name = $type instanceof \ReflectionNamedType ? $type->getName() : null;
		return $name !== null && is_subclass_of($name, \BackedEnum::class) ? $name : null;
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


	private static function shortenName(string $class): string
	{
		return substr($class, (int) strrpos($class, '\\') + 1);
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
