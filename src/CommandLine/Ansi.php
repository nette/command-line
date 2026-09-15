<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * Text with ANSI escape sequences measured and cut the way a terminal shows it: an escape sequence takes
 * no column, a grapheme cluster one and a wide character two.
 */
final class Ansi
{
	/** a CSI sequence (colors, cursor) or an OSC one (title, link) */
	private const Escape = '\e\[[\x30-\x3F]*[\x20-\x2F]*[\x40-\x7E]|\e\][^\x07\e]*(?:\x07|\e\\\\)';

	/** an escape sequence or one grapheme cluster */
	private const Token = '#' . self::Escape . '|\X#u';

	/** a cluster the terminal gives two columns: CJK, Hangul, fullwidth forms and emoji */
	private const Wide = '#^[\x{1100}-\x{115F}\x{2E80}-\x{303E}\x{3041}-\x{33FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}'
		. '\x{A000}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}'
		. '\x{FFE0}-\x{FFE6}\x{1F300}-\x{1F9FF}\x{20000}-\x{3FFFD}]#u';


	/**
	 * Removes every escape sequence, which leaves the text as it reads without a terminal.
	 */
	public static function strip(string $text): string
	{
		return (string) preg_replace('#' . self::Escape . '#', '', $text);
	}


	/**
	 * Returns the number of columns the text takes on the terminal.
	 */
	public static function measure(string $text): int
	{
		$width = 0;
		foreach (self::tokenize($text) as [, $columns]) {
			$width += $columns;
		}

		return $width;
	}


	/**
	 * Pads the text to the width; a text already that wide is returned as it is.
	 * @param  STR_PAD_LEFT|STR_PAD_RIGHT|STR_PAD_BOTH  $align
	 * @throws \InvalidArgumentException  when the padding character is not one column wide
	 */
	public static function pad(string $text, int $width, int $align = STR_PAD_RIGHT, string $char = ' '): string
	{
		if (self::measure($char) !== 1) {
			throw new \InvalidArgumentException("The padding character must be one column wide, '$char' given.");
		}

		$padding = $width - self::measure($text);
		if ($padding <= 0) {
			return $text;
		}

		return match ($align) {
			STR_PAD_LEFT => str_repeat($char, $padding) . $text,
			STR_PAD_BOTH => str_repeat($char, intdiv($padding, 2)) . $text . str_repeat($char, $padding - intdiv($padding, 2)),
			default => $text . str_repeat($char, $padding),
		};
	}


	/**
	 * Cuts the text to the width and marks the cut with the ellipsis, keeping the escape sequences so that a color
	 * is still closed. Cutting keeps the beginning, or with $keepEnd the end, where the name of a path is.
	 * An ellipsis that would not fit into the width is left out, so the result is never wider than asked.
	 */
	public static function truncate(string $text, int $width, string $ellipsis = '…', bool $keepEnd = false): string
	{
		if (self::measure($text) <= $width) {
			return $text;
		} elseif ($width <= 0) {
			return '';
		}

		$tokens = self::tokenize($text);
		$ellipsis = self::measure($ellipsis) > $width ? '' : $ellipsis;
		$budget = $width - self::measure($ellipsis);
		$keep = [];
		$full = false;
		foreach ($keepEnd ? array_reverse(array_keys($tokens)) : array_keys($tokens) as $i) {
			[, $columns] = $tokens[$i];
			if ($columns === 0) { // an escape sequence costs nothing and closes what it opened
				$keep[$i] = true;
			} elseif (!$full && $budget >= $columns) {
				$keep[$i] = true;
				$budget -= $columns;
			} else {
				$full = true;
			}
		}

		$out = '';
		$cut = false;
		foreach ($tokens as $i => [$token]) {
			if (isset($keep[$i])) {
				$out .= $token;
			} elseif (!$cut) { // the ellipsis stands where the first dropped cluster was
				$out .= $ellipsis;
				$cut = true;
			}
		}

		return $out;
	}


	/**
	 * @return list<array{string, int}>  the escape sequences and grapheme clusters of the text with their widths
	 */
	private static function tokenize(string $text): array
	{
		if (!preg_match_all(self::Token, $text, $m)) { // not UTF-8: every byte is a column
			$text = self::strip($text);
			return array_map(fn(string $byte) => [$byte, 1], $text === '' ? [] : str_split($text));
		}

		return array_map(
			fn(string $token) => [$token, match (true) {
				str_starts_with($token, "\e") => 0,
				(bool) preg_match(self::Wide, $token) => 2,
				default => 1,
			}],
			$m[0],
		);
	}
}
