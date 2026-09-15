<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function count, function_exists, is_array, is_resource;
use const PHP_OS_FAMILY, PHP_SAPI;


/**
 * Writes to a stream, in color where the stream takes it, and keeps a status of lines drawn in place,
 * which the next output replaces.
 */
final class Console
{
	/** @var array<string, int>  aixterm SGR codes: foreground 30-37 (dark) / 90-97 (bright), background = foreground + 10 */
	private const Colors = [
		'black' => 30, 'gray' => 90, 'silver' => 37, 'white' => 97,
		'navy' => 34, 'blue' => 94, 'green' => 32, 'lime' => 92,
		'teal' => 36, 'aqua' => 96, 'maroon' => 31, 'red' => 91,
		'purple' => 35, 'fuchsia' => 95, 'olive' => 33, 'yellow' => 93,
	];

	/** @var resource */
	private $stream;
	private bool $colors;
	private readonly bool $terminal;

	/** @var ?list<string>  the lines drawn in place, null when the status is gone */
	private ?array $status = null;
	private bool $restoresCursor = false;

	/** the cursor stands at the beginning of a line, so a status may be drawn without erasing one */
	private bool $atLineStart = true;
	private static ?int $measuredWidth = null;


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
	 * Writes the text as it is, in the color when one is given, and erases the status drawn before it. What comes
	 * from elsewhere and may carry colors this console must not pass on is filtered by the caller with Ansi::strip().
	 */
	public function write(string $text, ?string $color = null): void
	{
		$this->clearStatus();
		$this->emit($this->color($color, $text));
		if ($text !== '') {
			$this->atLineStart = str_ends_with($text, "\n");
		}
	}


	public function writeLine(string $text = '', ?string $color = null): void
	{
		$this->write($this->color($color, $text) . "\n");
	}


	/**
	 * Colors the text, or returns it as it is when the color is null or colors are off. The color is 'foreground'
	 * or 'foreground/background' (e.g. 'red', 'white/blue'), one of black, gray, silver, white, navy, blue, green,
	 * lime, teal, aqua, maroon, red, purple, fuchsia, olive and yellow.
	 * @throws \InvalidArgumentException  on an unknown color name
	 */
	public function color(?string $color, string $text): string
	{
		$names = $color === null ? [] : explode('/', $color);
		if (count($names) > 2 || in_array('', $names, true)) {
			throw new \InvalidArgumentException("Color '$color' is a foreground name, or 'foreground/background'.");
		}

		$codes = [];
		foreach ($names as $i => $name) {
			$codes[] = (self::Colors[$name] ?? throw new \InvalidArgumentException("Unknown color '$name'."))
				+ ($i ? 10 : 0); // the background is the foreground code plus ten
		}

		return $codes && $this->colors
			? "\e[" . implode(';', $codes) . 'm' . $text . "\e[0m"
			: $text;
	}


	public function useColors(bool $state): void
	{
		$this->colors = $state;
		if (
			$state
			&& PHP_OS_FAMILY === 'Windows'
			&& function_exists('sapi_windows_vt100_support')
			&& @stream_isatty($this->stream) // @ may trigger error 'cannot cast a filtered stream on this system'
		) {
			sapi_windows_vt100_support($this->stream, true);
		}
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
	 * Returns the width of the terminal in columns, or 80 when the stream is not one. COLUMNS holding a positive
	 * integer wins and is read every time, so a narrow terminal can be simulated; the terminal is asked only once.
	 */
	public function getWidth(): int
	{
		$columns = (string) getenv('COLUMNS');
		if (preg_match('#^[1-9]\d*$#D', $columns)) {
			return (int) $columns;
		} elseif (!$this->terminal) {
			return 80;
		}

		return self::$measuredWidth ??= self::measureTerminalWidth();
	}


	/**
	 * Draws the lines in place of the ones drawn before, a progress bar or a panel; the next write() erases them and
	 * the next setStatus() draws them below its output. A line too long is cut, since a wrapped one cannot be redrawn,
	 * and nothing to draw erases the status. Nothing is drawn when the stream is not a terminal.
	 * @param  string|list<string>  $lines
	 */
	public function setStatus(string|array $lines): void
	{
		if (!$this->terminal) {
			return;
		} elseif ($lines === '' || $lines === []) {
			$this->clearStatus();
			return;
		}

		$lines = is_array($lines) ? $lines : explode("\n", $lines);
		$width = $this->getWidth();
		$out = $this->status !== null
			? "\e[J" // erase what is drawn, the cursor stands at its first line
			: ($this->atLineStart ? '' : "\n") // a status takes whole lines, so it never erases a half-written one
				. "\e[?25l"; // hide the cursor, which would jump around the status
		$out .= implode("\n", array_map(fn(string $line) => Ansi::truncate($line, $width), $lines));

		// back to the first line of the status, where the next write() erases from
		$this->emit($out . "\r" . (count($lines) > 1 ? "\e[" . (count($lines) - 1) . 'A' : ''));
		$this->status = $lines;
		$this->atLineStart = true;

		if (!$this->restoresCursor) { // a process that dies must not leave the cursor hidden
			$this->restoresCursor = true;
			register_shutdown_function($this->clearStatus(...));
		}
	}


	/**
	 * Erases the status and shows the cursor again.
	 */
	public function clearStatus(): void
	{
		if ($this->status !== null) {
			$this->status = null;
			$this->emit("\e[J\e[?25h");
		}
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
	 * A terminal takes colors, unless NO_COLOR says otherwise (https://no-color.org); FORCE_COLOR decides
	 * whatever the stream is. Outside a CLI nothing does, so that no escape sequence reaches a page.
	 */
	private static function detectColors(bool $terminal): bool
	{
		if ((PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') || (string) getenv('NO_COLOR') !== '') {
			return false;
		}

		$force = getenv('FORCE_COLOR');
		return $force === false || $force === ''
			? $terminal
			: $force !== '0' && strtolower($force) !== 'false';
	}


	private static function measureTerminalWidth(): int
	{
		if (!function_exists('exec')) { // disabled in the configuration of PHP
			return 80;
		} elseif (PHP_OS_FAMILY !== 'Windows') {
			return (int) @exec('tput cols 2>/dev/null') ?: 80;
		}

		$lines = [];
		@exec('mode con 2>NUL', $lines);
		$values = [];
		foreach ($lines as $line) {
			if (preg_match('#:\s+(\d+)\s*$#', $line, $m)) {
				$values[] = (int) $m[1];
			}
		}

		return $values[1] ?? 80; // second numeric value is columns (locale-independent)
	}


	private function emit(string $text): void
	{
		if (is_resource($this->stream)) { // a shutdown function may find the stream closed
			fwrite($this->stream, $text);
		}
	}
}
