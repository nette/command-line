<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use const PHP_SAPI;


/**
 * Writes to a stream, in color where the stream takes it.
 */
final class Console
{
	/** @var resource */
	private $stream;
	private bool $colors;
	private readonly bool $terminal;


	/**
	 * @param  ?resource  $stream  where the output goes; STDOUT by default
	 * @param  ?bool  $colors  null asks the stream and honors NO_COLOR and FORCE_COLOR
	 * @param  ?bool  $terminal  null asks the stream; a test over a memory stream passes true
	 */
	public function __construct($stream = null, ?bool $colors = null, ?bool $terminal = null)
	{
		$this->stream = $stream ?? STDOUT;
		$this->terminal = $terminal ?? self::detectTerminal($this->stream);
		$this->useColors($colors ?? self::detectColors($this->terminal));
	}


	/**
	 * Writes the text as it is. What comes from elsewhere and may carry colors this console must not
	 * pass on is filtered by the caller with Ansi::strip().
	 */
	public function write(string $text): void
	{
		fwrite($this->stream, $text);
	}


	public function writeLine(string $text = ''): void
	{
		$this->write($text . "\n");
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
		if ($this->colors) {
			$c = explode('/', $color ?: '/');
			return "\033["
				. ($c[0] ? $colors[$c[0]] : '')
				. (empty($c[1]) ? '' : ';4' . substr($colors[$c[1]], -1))
				. 'm' . $s
				. ($s === null ? '' : "\033[0m");
		}

		return (string) $s;
	}


	public function useColors(bool $state): void
	{
		$this->colors = $state;
	}


	public function hasColors(): bool
	{
		return $this->colors;
	}


	/**
	 * Tells whether the output is an interactive terminal, which is what a progress bar or a prompt asks:
	 * a user may turn colors off and still sit at a real terminal.
	 */
	public function isTerminal(): bool
	{
		return $this->terminal;
	}


	/**
	 * @param  resource  $stream
	 */
	private static function detectTerminal($stream): bool
	{
		return (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg')
			&& @stream_isatty($stream); // @ may trigger error 'cannot cast a filtered stream on this system'
	}


	/**
	 * Detects whether the terminal supports ANSI colors.
	 * Returns false when NO_COLOR is set, or when not running in a CLI TTY.
	 * FORCE_COLOR overrides the TTY check.
	 */
	private static function detectColors(bool $terminal): bool
	{
		return (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg')
			&& getenv('NO_COLOR') === false // https://no-color.org
			&& (getenv('FORCE_COLOR') || $terminal);
	}
}
