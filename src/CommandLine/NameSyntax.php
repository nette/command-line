<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * The grammar of names, which follows only what the parser can read.
 * @internal
 */
final class NameSyntax
{
	/**
	 * Refuses a name or an alias of an option other than one or two dashes and a letter, digit or underscore,
	 * with no '=', whitespace or control character.
	 */
	public static function assertOptionName(string $name, ?string $alias = null): void
	{
		foreach ([$name, $alias] as $id) {
			if ($id === null) {
				continue;
			}

			$label = $id === $name ? "Option name '$id'" : "Alias '$id' of option $name";
			if (!str_starts_with($id, '-')) {
				throw new \InvalidArgumentException($id === $name ? "$label must start with a dash, e.g. --$id." : "$label must start with a dash.");
			} elseif (!preg_match('#^--?\w[^\s=\x00-\x1F\x7F]*$#D', $id)) {
				throw new \InvalidArgumentException("$label is not valid: after one or two dashes start with a letter, digit or underscore and use no '=', whitespace or control characters.");
			}
		}
	}


	/**
	 * Refuses a name of an argument or a command other than a letter, digit or underscore followed by no whitespace
	 * or control character.
	 * @param  'Argument'|'Command'  $kind
	 */
	public static function assertWordName(string $name, string $kind): void
	{
		if (str_starts_with($name, '-')) {
			throw new \InvalidArgumentException("$kind name '$name' must not start with a dash.");
		} elseif (!preg_match('#^\w[^\s\x00-\x1F\x7F]*$#D', $name)) {
			throw new \InvalidArgumentException("$kind name '$name' is not valid: start with a letter, digit or underscore and use no whitespace or control characters.");
		}
	}
}
