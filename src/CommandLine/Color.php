<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function strlen;


/**
 * One color as Console::color() takes it, drawn as near as the depth of a console allows. Every color has its RGB;
 * a name also has the SGR code the terminal draws in its theme's colors, and an index its number of the palette.
 * @internal
 */
final class Color
{
	/**
	 * @var array<string, array{int, int}>  name => [aixterm SGR code, RGB as xterm draws it]; the code of the foreground
	 * is 30-37 (dark) or 90-97 (bright), of the background the foreground code plus ten
	 */
	private const Names = [
		'black' => [30, 0x000000], 'gray' => [90, 0x7F7F7F], 'silver' => [37, 0xE5E5E5], 'white' => [97, 0xFFFFFF],
		'navy' => [34, 0x0000EE], 'blue' => [94, 0x5C5CFF], 'green' => [32, 0x00CD00], 'lime' => [92, 0x00FF00],
		'teal' => [36, 0x00CDCD], 'aqua' => [96, 0x00FFFF], 'maroon' => [31, 0xCD0000], 'red' => [91, 0xFF0000],
		'purple' => [35, 0xCD00CD], 'fuchsia' => [95, 0xFF00FF], 'olive' => [33, 0xCDCD00], 'yellow' => [93, 0xFFFF00],
	];

	/** the intensities of one component in the cube 6x6x6 of the extended palette */
	private const CubeLevels = [0, 95, 135, 175, 215, 255];

	/** the least spread of the components that makes a color a hue rather than a shade of gray */
	private const MinChroma = 32;


	private function __construct(
		/** 0xRRGGBB */
		private readonly int $rgb,
		/** the SGR code of a name */
		private readonly ?int $code = null,
		/** the number of the extended palette */
		private readonly ?int $index = null,
	) {
	}


	/**
	 * Reads a name, a number 0-255 of the extended palette, '#RRGGBB' or '#RGB'.
	 * @throws \InvalidArgumentException  on an unknown color
	 */
	public static function parse(string $color): self
	{
		if (isset(self::Names[$color])) {
			[$code, $rgb] = self::Names[$color];
			return new self($rgb, code: $code);

		} elseif (preg_match('~^(0|[1-9]\d{0,2})$~D', $color) && (int) $color <= 255) {
			return new self(self::computeIndexRgb((int) $color), index: (int) $color);

		} elseif (preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~Di', $color, $m)) {
			$hex = strlen($m[1]) === 3 ? (string) preg_replace('~.~', '$0$0', $m[1]) : $m[1];
			return new self((int) hexdec($hex));
		}

		throw new \InvalidArgumentException("Unknown color '$color'.");
	}


	/**
	 * Returns the SGR parameters of the color at the depth: a name by its code, an index by its number where the
	 * palette is taken, an RGB exactly in true color, and otherwise the nearest color the depth has.
	 */
	public function toSgr(ColorDepth $depth, bool $background = false): string
	{
		$prefix = $background ? '48;' : '38;';
		return match (true) {
			$this->code !== null => (string) ($this->code + ($background ? 10 : 0)),
			$depth === ColorDepth::TrueColor && $this->index === null => $prefix . '2;' . ($this->rgb >> 16) . ';' . ($this->rgb >> 8 & 0xFF) . ';' . ($this->rgb & 0xFF),
			$depth->value >= ColorDepth::Ansi256->value => $prefix . '5;' . ($this->index ?? self::findNearestIndex($this->rgb)),
			default => (string) (self::findNearestCode($this->rgb) + ($background ? 10 : 0)),
		};
	}


	/**
	 * Returns the RGB of a color of the extended palette: the 16 of the terminal, a cube 6x6x6 and 24 grays.
	 */
	private static function computeIndexRgb(int $index): int
	{
		if ($index < 16) {
			return array_column(self::Names, 1, 0)[$index < 8 ? 30 + $index : 82 + $index];
		} elseif ($index < 232) {
			$index -= 16;
			return self::CubeLevels[intdiv($index, 36)] << 16 | self::CubeLevels[intdiv($index, 6) % 6] << 8 | self::CubeLevels[$index % 6];
		}

		return (8 + 10 * ($index - 232)) * 0x010101;
	}


	/**
	 * Finds the color of the cube or the grays nearest to the RGB; the 16 of the terminal are left out, since the
	 * theme decides what they look like.
	 */
	private static function findNearestIndex(int $rgb): int
	{
		$level = function (int $component): int {
			$distances = array_map(fn(int $level) => abs($level - $component), self::CubeLevels);
			return (int) array_search(min($distances), $distances, true);
		};
		$cube = 16 + 36 * $level($rgb >> 16) + 6 * $level($rgb >> 8 & 0xFF) + $level($rgb & 0xFF);
		$average = (($rgb >> 16) + ($rgb >> 8 & 0xFF) + ($rgb & 0xFF)) / 3;
		$gray = 232 + max(0, min(23, (int) round(($average - 8) / 10)));
		return self::measureDistance($rgb, self::computeIndexRgb($gray)) < self::measureDistance($rgb, self::computeIndexRgb($cube))
			? $gray
			: $cube;
	}


	/**
	 * Finds the SGR code of the named color nearest to the RGB, as xterm draws them: a hue by the nearest hue and then
	 * the nearer lightness of its dark and bright name, a gray by the nearest lightness of the grays. A plain distance
	 * of RGB would give most pastel colors a gray, which is nearer to them than any of the saturated names.
	 */
	private static function findNearestCode(int $rgb): int
	{
		[$hue, $lightness] = self::measureHue($rgb);
		$nearest = 0;
		$least = PHP_FLOAT_MAX;
		foreach (self::Names as [$code, $color]) {
			[$nameHue, $nameLightness] = self::measureHue($color);
			if (($hue === null) !== ($nameHue === null)) {
				continue;
			}

			$hueDistance = $hue === null ? 0 : min(abs($hue - $nameHue), 360 - abs($hue - $nameHue));
			$distance = $hueDistance * 1000 + abs($lightness - $nameLightness); // the hue decides first
			if ($distance < $least) {
				[$nearest, $least] = [$code, $distance];
			}
		}

		return $nearest;
	}


	/**
	 * Returns the hue in degrees, null for a shade of gray, and the lightness 0-255 of the RGB.
	 * @return array{?float, float}
	 */
	private static function measureHue(int $rgb): array
	{
		[$r, $g, $b] = [$rgb >> 16, $rgb >> 8 & 0xFF, $rgb & 0xFF];
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$chroma = $max - $min;
		$hue = match (true) {
			$chroma < self::MinChroma => null,
			$max === $r => fmod(60 * ($g - $b) / $chroma + 360, 360),
			$max === $g => 60 * ($b - $r) / $chroma + 120,
			default => 60 * ($r - $g) / $chroma + 240,
		};
		return [$hue, ($max + $min) / 2];
	}


	/** Returns the squared distance of two RGB colors. */
	private static function measureDistance(int $a, int $b): int
	{
		return (($a >> 16) - ($b >> 16)) ** 2 + (($a >> 8 & 0xFF) - ($b >> 8 & 0xFF)) ** 2 + (($a & 0xFF) - ($b & 0xFF)) ** 2;
	}
}
