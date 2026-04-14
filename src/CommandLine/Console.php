<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * Stupid console writer.
 */
class Console
{
	private bool $useColors;


	public function __construct()
	{
		$this->useColors = self::detectColors();
	}


	public function useColors(bool $state = true): void
	{
		$this->useColors = $state;
	}


	/**
	 * Wraps string in ANSI color codes, or returns plain string when colors are disabled.
	 * Color format: 'foreground' or 'foreground/background' (e.g. 'red', 'white/blue').
	 * When $s is null, emits the escape code without a reset sequence.
	 * Available colors: black, gray, silver, white, navy, blue, green, lime,
	 * teal, aqua, maroon, red, purple, fuchsia, olive, yellow.
	 */
	public function color(?string $color, ?string $s = null): string
	{
		$colors = [
			'black' => '0;30', 'gray' => '1;30', 'silver' => '0;37', 'white' => '1;37',
			'navy' => '0;34', 'blue' => '1;34', 'green' => '0;32', 'lime' => '1;32',
			'teal' => '0;36', 'aqua' => '1;36', 'maroon' => '0;31', 'red' => '1;31',
			'purple' => '0;35', 'fuchsia' => '1;35', 'olive' => '0;33', 'yellow' => '1;33',
			'' => '0',
		];
		if ($this->useColors) {
			$c = explode('/', $color ?: '/');
			return "\033["
				. ($c[0] ? $colors[$c[0]] : '')
				. (empty($c[1]) ? '' : ';4' . substr($colors[$c[1]], -1))
				. 'm' . $s
				. ($s === null ? '' : "\033[0m");
		}

		return (string) $s;
	}


	/**
	 * Detects whether the terminal supports ANSI colors.
	 * Returns false when NO_COLOR is set, or when not running in a CLI TTY.
	 * FORCE_COLOR overrides the TTY check.
	 */
	public static function detectColors(): bool
	{
		return (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg')
			&& getenv('NO_COLOR') === false // https://no-color.org
			&& (getenv('FORCE_COLOR') || self::detectTerminal());
	}


	/**
	 * Detects whether the output is an interactive terminal.
	 * Useful for auto-disabling features that only make sense in a TTY
	 * (progress indicators, line-rewriting output, interactive prompts).
	 */
	public static function detectTerminal(): bool
	{
		return (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg')
			&& @stream_isatty(STDOUT); // @ may trigger error 'cannot cast a filtered stream on this system'
	}
}
