<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function strlen;


/**
 * Text with ANSI escape sequences measured and cut the way a terminal shows it: an escape sequence takes
 * no column, a grapheme cluster one and a wide one two. Where unsure, it measures wider, never narrower,
 * so that nothing cut to a width overflows; East Asian Ambiguous characters are narrow. It measures
 * printable text: a control character such as a tab takes no column.
 */
final class Ansi
{
	/**
	 * a CSI sequence (colors, cursor), a string one (OSC title or link, DCS, SOS, PM, APC) or a short one, the ST
	 * ending a string one included; one left unterminated ends at the first byte that does not belong to it, as on
	 * a terminal
	 */
	private const EscapeSequence = '\e(?:\[[\x20-\x3F]*[\x40-\x7E]?|\][^\x07\e]*\x07?|[PX^_][^\e]*|[\x20-\x2F]*[\x30-\x7E]?)';

	/** an escape sequence or one grapheme cluster */
	private const Token = '#' . self::EscapeSequence . '|\X#u';

	/** the characters a terminal gives two columns, from Unicode 17.0.0 by tools/generate-width-table.php */
	private const WideCharacters = '\x{1100}-\x{115F}\x{231A}-\x{231B}\x{2329}-\x{232A}\x{23E9}-\x{23EC}\x{23F0}\x{23F3}'
		. '\x{25FD}-\x{25FE}\x{2614}-\x{2615}\x{2630}-\x{2637}\x{2648}-\x{2653}\x{267F}\x{268A}-\x{268F}'
		. '\x{2693}\x{26A1}\x{26AA}-\x{26AB}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26CE}\x{26D4}\x{26EA}'
		. '\x{26F2}-\x{26F3}\x{26F5}\x{26FA}\x{26FD}\x{2705}\x{270A}-\x{270B}\x{2728}\x{274C}\x{274E}'
		. '\x{2753}-\x{2755}\x{2757}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}'
		. '\x{2E80}-\x{2E99}\x{2E9B}-\x{2EF3}\x{2F00}-\x{2FD5}\x{2FF0}-\x{3029}\x{302E}-\x{303E}'
		. '\x{3041}-\x{3096}\x{309B}-\x{30FF}\x{3105}-\x{312F}\x{3131}-\x{318E}\x{3190}-\x{31E5}'
		. '\x{31EF}-\x{321E}\x{3220}-\x{3247}\x{3250}-\x{A48C}\x{A490}-\x{A4C6}\x{A960}-\x{A97C}'
		. '\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE52}\x{FE54}-\x{FE66}'
		. '\x{FE68}-\x{FE6B}\x{FF01}-\x{FF60}\x{FFE0}-\x{FFE6}\x{16FE0}-\x{16FE3}\x{16FF0}-\x{16FF6}'
		. '\x{17000}-\x{18CD5}\x{18CFF}-\x{18D1E}\x{18D80}-\x{18DF2}\x{1AFF0}-\x{1AFF3}\x{1AFF5}-\x{1AFFB}'
		. '\x{1AFFD}-\x{1AFFE}\x{1B000}-\x{1B122}\x{1B132}\x{1B150}-\x{1B152}\x{1B155}\x{1B164}-\x{1B167}'
		. '\x{1B170}-\x{1B2FB}\x{1D300}-\x{1D356}\x{1D360}-\x{1D376}\x{1F004}\x{1F0CF}\x{1F18E}'
		. '\x{1F191}-\x{1F19A}\x{1F1E6}-\x{1F202}\x{1F210}-\x{1F23B}\x{1F240}-\x{1F248}\x{1F250}-\x{1F251}'
		. '\x{1F260}-\x{1F265}\x{1F300}-\x{1F320}\x{1F32D}-\x{1F335}\x{1F337}-\x{1F37C}\x{1F37E}-\x{1F393}'
		. '\x{1F3A0}-\x{1F3CA}\x{1F3CF}-\x{1F3D3}\x{1F3E0}-\x{1F3F0}\x{1F3F4}\x{1F3F8}-\x{1F43E}\x{1F440}'
		. '\x{1F442}-\x{1F4FC}\x{1F4FF}-\x{1F53D}\x{1F54B}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F57A}'
		. '\x{1F595}-\x{1F596}\x{1F5A4}\x{1F5FB}-\x{1F64F}\x{1F680}-\x{1F6C5}\x{1F6CC}\x{1F6D0}-\x{1F6D2}'
		. '\x{1F6D5}-\x{1F6D8}\x{1F6DC}-\x{1F6DF}\x{1F6EB}-\x{1F6EC}\x{1F6F4}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}'
		. '\x{1F7F0}\x{1F90C}-\x{1F93A}\x{1F93C}-\x{1F945}\x{1F947}-\x{1F9FF}\x{1FA70}-\x{1FA7C}'
		. '\x{1FA80}-\x{1FA8A}\x{1FA8E}-\x{1FAC6}\x{1FAC8}\x{1FACD}-\x{1FADC}\x{1FADF}-\x{1FAEA}'
		. '\x{1FAEF}-\x{1FAF8}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}';

	/** a cluster of characters that take no column: combining marks, format and control characters */
	private const ZeroWidthCluster = '#^(?:(?![\x{AD}\x{600}-\x{605}\x{6DD}\x{70F}\x{890}\x{891}\x{8E2}\x{110BD}\x{110CD}])'
		. '[\p{Mn}\p{Me}\p{Cf}\p{Cc}])+$#u';


	/**
	 * Removes every escape sequence in the 7-bit form; other control characters stay, so it is no sanitizer of
	 * untrusted text.
	 */
	public static function strip(string $text): string
	{
		return (string) preg_replace('#' . self::EscapeSequence . '#', '', $text);
	}


	/**
	 * Returns the number of columns the text takes on the terminal.
	 */
	public static function measure(string $text): int
	{
		if (!preg_match('#[\x80-\xFF]#', $text)) { // ASCII, where a column is a byte
			$text = self::strip($text);
			if (!preg_match('#[\x00-\x1F\x7F]#', $text)) {
				return strlen($text);
			}
		}

		$width = 0;
		foreach (self::tokenize($text) as [, $columns]) {
			$width += (int) $columns;
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
			if ($columns === null) { // an escape sequence costs nothing and closes what it opened
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
	 * @return list<array{string, ?int}>  the escape sequences, with null, and the grapheme clusters of the text with their widths
	 */
	private static function tokenize(string $text): array
	{
		if (!preg_match_all(self::Token, $text, $m)) { // not UTF-8: every byte but a control one is a column
			$text = self::strip($text);
			return array_map(
				fn(string $byte) => [$byte, preg_match('#[\x00-\x1F\x7F]#', $byte) ? 0 : 1],
				$text === '' ? [] : str_split($text),
			);
		}

		return array_map(
			fn(string $token) => [$token, str_starts_with($token, "\e") ? null : self::measureCluster($token)],
			$m[0],
		);
	}


	/**
	 * Returns 2 for a cluster with a wide character or an emoji presentation, 0 for one with nothing to show.
	 */
	private static function measureCluster(string $cluster): int
	{
		return match (true) {
			strlen($cluster) === 1 => preg_match('#[\x00-\x1F\x7F]#', $cluster) ? 0 : 1,
			(bool) preg_match('#[' . self::WideCharacters . '\x{FE0F}]#u', $cluster) => 2,
			(bool) preg_match(self::ZeroWidthCluster, $cluster) => 0,
			default => 1,
		};
	}
}
