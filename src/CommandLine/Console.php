<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function count, function_exists, in_array;
use const PHP_OS_FAMILY, PHP_SAPI;


/**
 * Writes to a stream, in color where the stream takes it.
 */
final class Console
{
	/** @var resource */
	private $stream;
	private ColorDepth $colorDepth;
	private readonly bool $terminal;


	/**
	 * @param  ?resource  $stream  where the output goes, a blocking stream open for writing; STDOUT by default
	 * @param  ?ColorDepth  $colorDepth  null asks the stream and honors NO_COLOR, FORCE_COLOR and COLORTERM
	 * @param  ?bool  $terminal  null asks the stream; a test over a memory stream passes true
	 */
	public function __construct($stream = null, ?ColorDepth $colorDepth = null, ?bool $terminal = null)
	{
		$this->stream = $stream ?? STDOUT;
		if (!preg_match('#[waxc+]#', stream_get_meta_data($this->stream)['mode'])) {
			throw new \InvalidArgumentException('The stream is not open for writing.');
		}

		$this->terminal = $terminal ?? self::detectTerminal($this->stream);
		$this->colorDepth = $colorDepth ?? self::detectColorDepth($this->terminal);
		if (
			PHP_OS_FAMILY === 'Windows'
			&& function_exists('sapi_windows_vt100_support')
			&& @stream_isatty($this->stream) // @ may trigger error 'cannot cast a filtered stream on this system'
		) {
			sapi_windows_vt100_support($this->stream, true);
		}
	}


	/**
	 * Writes the text as it is, in the color when one is given.
	 */
	public function write(string $text, ?string $color = null): void
	{
		$this->emit($this->color($color, $text));
	}


	public function writeLine(string $text = '', ?string $color = null): void
	{
		$this->write($this->color($color, $text) . "\n");
	}


	/**
	 * Colors the text, or returns it as it is when the color is null or colors are off. The color is 'foreground'
	 * or 'foreground/background' (e.g. 'red', 'white/blue'), each of them one of black, gray, silver, white, navy,
	 * blue, green, lime, teal, aqua, maroon, red, purple, fuchsia, olive and yellow, which the terminal draws in the
	 * colors of its theme, or a color the theme does not change: a number 0-255 of the extended palette (e.g. '117')
	 * or '#RRGGBB' and '#RGB'. Such a color is drawn as near as the depth of the console allows.
	 * @throws \InvalidArgumentException  on an unknown color name
	 */
	public function color(?string $color, string $text): string
	{
		$names = $color === null ? [] : explode('/', $color);
		if (count($names) > 2 || in_array('', $names, true)) {
			throw new \InvalidArgumentException("Color '$color' is a foreground name, or 'foreground/background'.");
		}

		$colors = array_map(Color::parse(...), $names); // an unknown name is refused even when colors are off
		if (!$colors || $this->colorDepth === ColorDepth::None) {
			return $text;
		}

		$codes = [];
		foreach ($colors as $i => $parsed) {
			$codes[] = $parsed->toSgr($this->colorDepth, background: $i === 1);
		}

		return "\e[" . implode(';', $codes) . 'm' . $text . "\e[0m";
	}


	public function setColorDepth(ColorDepth $depth): void
	{
		$this->colorDepth = $depth;
	}


	public function hasColors(): bool
	{
		return $this->colorDepth !== ColorDepth::None;
	}


	public function getColorDepth(): ColorDepth
	{
		return $this->colorDepth;
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
	 * A terminal takes the extended palette, and any color when COLORTERM says so. NO_COLOR turns colors off
	 * (https://no-color.org), FORCE_COLOR turns them on whatever the stream is, its level 1-3 being the least depth
	 * (https://force-color.org). Outside a CLI nothing takes colors, so that no escape sequence reaches a page.
	 */
	private static function detectColorDepth(bool $terminal): ColorDepth
	{
		$force = strtolower((string) getenv('FORCE_COLOR'));
		if (
			(PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg')
			|| (string) getenv('NO_COLOR') !== ''
			|| $force === '0'
			|| $force === 'false'
			|| (!$terminal && $force === '')
		) {
			return ColorDepth::None;
		}

		$detected = match (true) {
			in_array(strtolower((string) getenv('COLORTERM')), ['truecolor', '24bit'], true) => ColorDepth::TrueColor,
			$terminal => ColorDepth::Ansi256,
			default => ColorDepth::None,
		};
		$forced = match (true) {
			$force === '' => ColorDepth::None,
			(bool) preg_match('#^\d+$#D', $force) => ColorDepth::from(min((int) $force, ColorDepth::TrueColor->value)),
			default => ColorDepth::Ansi16, // true, yes
		};
		return $forced->value > $detected->value ? $forced : $detected;
	}


	private function emit(string $text): void
	{
		@fwrite($this->stream, $text); // @ a pipe closed by the reader (| head) is no error of the application
	}
}
