<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function count, function_exists, in_array, is_resource;
use const PHP_OS_FAMILY, PHP_SAPI;


/**
 * Writes to a stream, in color where the stream takes it, and keeps a status of lines drawn in place,
 * which the next output replaces.
 */
final class Console
{
	/** @var resource */
	private $stream;
	private ColorDepth $colorDepth;
	private readonly bool $terminal;

	/** a status is drawn and the cursor stands at its first line */
	private bool $statusShown = false;
	private bool $restoresCursor = false;

	/** the cursor stands at the beginning of a line, so a status may be drawn without erasing one */
	private bool $atLineStart = true;
	private ?int $measuredWidth = null;


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
			sapi_windows_vt100_support($this->stream, true); // a status needs it as much as colors do
		}
	}


	/**
	 * A status lives as long as its console, so a console let go does not leave the cursor hidden.
	 */
	public function __destruct()
	{
		$this->clearStatus();
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
	 * Returns the width of the terminal in columns, or 80 when the stream is not one. COLUMNS holding a positive
	 * integer wins and is read every time, so a narrow terminal can be simulated; the terminal is asked once per
	 * console, so a resized one needs COLUMNS or a new console.
	 */
	public function getWidth(): int
	{
		$columns = (string) getenv('COLUMNS');
		if (preg_match('#^[1-9]\d*$#D', $columns)) {
			return (int) $columns;
		} elseif (!$this->terminal) {
			return 80;
		}

		return $this->measuredWidth ??= self::measureTerminalWidth();
	}


	/**
	 * Draws the lines in place of the ones drawn before, a progress bar or a panel; the next write() erases them and
	 * the next setStatus() draws them below its output. A line too long is cut, since a wrapped one cannot be redrawn,
	 * and nothing to draw erases the status. Nothing is drawn when the stream is not a terminal. A line is printable
	 * text with colors; a tab is expanded, other control characters and moves of the cursor are the caller's risk.
	 * @param  string|list<string>  $lines
	 */
	public function setStatus(string|array $lines): void
	{
		$text = str_replace("\r\n", "\n", implode("\n", (array) $lines));
		if (!$this->terminal) {
			return;
		} elseif ($text === '') {
			$this->clearStatus();
			return;
		}

		$lines = explode("\n", $text);
		$width = $this->getWidth();
		$out = $this->statusShown
			? "\e[J" // erase what is drawn, the cursor stands at its first line
			: ($this->atLineStart ? '' : "\n") // a status takes whole lines, so it never erases a half-written one
				. "\e[?25l"; // hide the cursor, which would jump around the status
		$out .= implode("\n", array_map(fn(string $line) => Ansi::truncate(self::expandTabs($line), $width), $lines));

		// back to the first line of the status, where the next write() erases from
		$this->emit($out . "\r" . (count($lines) > 1 ? "\e[" . (count($lines) - 1) . 'A' : ''));
		$this->statusShown = true;
		$this->atLineStart = true;

		if (!$this->restoresCursor) { // a fatal error runs no destructor, yet must not leave the cursor hidden
			$this->restoresCursor = true;
			$console = \WeakReference::create($this); // the shutdown function must not keep the console alive
			register_shutdown_function(static fn() => $console->get()?->clearStatus());
		}
	}


	/**
	 * Replaces the tabs with spaces up to the stops of the terminal, every 8 columns, which a line of the status
	 * reaches exactly, since it starts in the first one.
	 */
	private static function expandTabs(string $line): string
	{
		$out = '';
		foreach (explode("\t", $line) as $i => $part) {
			$out .= ($i ? str_repeat(' ', 8 - Ansi::measure($out) % 8) : '') . $part;
		}

		return $out;
	}


	/**
	 * Erases the status and shows the cursor again.
	 */
	public function clearStatus(): void
	{
		if ($this->statusShown) {
			$this->statusShown = false;
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


	private static function measureTerminalWidth(): int
	{
		if (!function_exists('exec')) { // disabled in the configuration of PHP
			return 80;
		} elseif (PHP_OS_FAMILY !== 'Windows') {
			// the terminal of the process, which exec() would not see through a redirected stdin
			$size = (string) @exec('stty size 2>/dev/null </dev/tty');
			return preg_match('#^\d+ ([1-9]\d*)$#D', trim($size), $m) ? (int) $m[1] : 80;
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
			@fwrite($this->stream, $text); // @ a pipe closed by the reader (| head) is no error of the application
		}
	}
}
