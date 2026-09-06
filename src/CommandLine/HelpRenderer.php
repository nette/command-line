<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Help\{Section, Text};
use Nette\CommandLine\Parameters\{Argument, Flag, Option, Parameter};
use function count, is_bool, is_scalar, strlen;


/**
 * Draws the help of a command. The colored output is the plain one wrapped in escape
 * sequences, so stripping them gives the plain one back.
 */
final class HelpRenderer
{
	/** role => color of Console::color(); every role the renderer draws with has to be here */
	private const Theme = [
		'heading' => '#D7AF5F',
		'option' => '#87AFD7',
		'argument' => '#D78787', // as a value
		'command' => '#87D787',
		'value' => '#D78787',
		'default' => '#767676', // also the dots of a repeatable parameter
	];

	private const MaxSyntaxWidth = 28;
	private const MinDescriptionWidth = 20;
	private const Indent = '  ';


	/**
	 * Without a console, render() writes to a new one on STDOUT and renderToString() returns plain text.
	 * Descriptions are wrapped to the width, the terminal's when none is given; the usage, a text, a long syntax or a
	 * long word overflows.
	 */
	public function __construct(
		private readonly ?Console $console = null,
		private readonly ?int $width = null,
	) {
	}


	/**
	 * Writes the help of the command to the console.
	 */
	public function render(Command $command): void
	{
		if ($this->console === null) {
			(new self(new Console, $this->width))->render($command);
		} else {
			$this->console->write($this->renderToString($command));
		}
	}


	/**
	 * Returns the help of the command, colored when there is a console.
	 */
	public function renderToString(Command $command): string
	{
		$items = self::collectItems($command);
		$column = strlen(self::Indent) + $this->syntaxWidth($items) + 2;
		$width = $this->width ?? ($this->console ?? new Console)->getWidth();

		$blocks = $block = [];
		foreach ($items as $item) {
			if ($item instanceof Parameter || $item instanceof Command) {
				array_push($block, ...$this->renderEntry($item, $column, $width));
				continue;
			}

			$blocks[] = implode("\n", $block);
			$block = $item instanceof Section
				? [$this->style('heading', $item->title . ':')]
				: [];
			if ($item instanceof Text) {
				$blocks[] = $item->text;
			}
		}

		$blocks[] = implode("\n", $block);
		$usage = $this->renderUsage($command->usage ?? [$this->generateUsage($command)]);
		$description = implode("\n", $this->wrap($this->describe($command), $width));
		return implode("\n\n", array_filter([$usage, $description, ...$blocks], fn($s) => $s !== '')) . "\n";
	}


	/**
	 * The items of the command without the hidden parameters, with headings where the author wrote none,
	 * followed by the options it inherits from the commands above.
	 * @return list<Flag|Option|Argument|Command|Section|Text>
	 */
	private static function collectItems(Command $command): array
	{
		$own = array_values(array_filter(
			$command->getItems(),
			fn($item) => !$item instanceof Parameter || !$item->hidden,
		));

		$inherited = [];
		foreach (array_slice($command->getPath(), 0, -1) as $ancestor) {
			foreach ($ancestor->getOptions() as $option) {
				if (!$option->hidden) {
					$inherited[] = $option;
				}
			}
		}

		if (!$inherited) {
			return self::withHeadings($own);
		}

		$hasOwnOptions = (bool) array_filter($command->getOptions(), fn($option) => !$option->hidden);
		return [...self::withHeadings($own), new Section($hasOwnOptions ? 'Global options' : 'Options'), ...$inherited];
	}


	/**
	 * Puts an "Options:", "Arguments:" or "Commands:" heading in front of every run of items of one kind, but only
	 * when the author wrote no heading at all; one of their own means they arrange the help.
	 * @param  list<Flag|Option|Argument|Command|Section|Text>  $items
	 * @return list<Flag|Option|Argument|Command|Section|Text>
	 */
	private static function withHeadings(array $items): array
	{
		foreach ($items as $item) {
			if ($item instanceof Section) {
				return $items;
			}
		}

		$out = [];
		$last = null;
		foreach ($items as $item) {
			$heading = match (true) {
				$item instanceof Flag, $item instanceof Option => 'Options',
				$item instanceof Argument => 'Arguments',
				$item instanceof Command => 'Commands',
				default => null,
			};
			if ($heading !== null && $heading !== $last) {
				$last = $heading;
				$out[] = new Section($heading);
			}

			$out[] = $item;
		}

		return $out;
	}


	/** @param  list<string>  $lines */
	private function renderUsage(array $lines): string
	{
		$heading = $this->style('heading', 'Usage:');
		return count($lines) === 1
			? "$heading " . $lines[0]
			: "$heading\n" . implode("\n", array_map(fn($line) => self::Indent . $line, $lines));
	}


	private function generateUsage(Command $command): string
	{
		$root = $command->getRoot();
		$parts = [$this->style('command', $root->name ?? basename((string) ($_SERVER['argv'][0] ?? 'program')))];
		if ($command !== $root) {
			$parts[] = $this->style('command', $command->getFullName());
		}

		foreach ($command->getPath() as $level) {
			if (array_filter($level->getOptions(), fn($option) => !$option->hidden)) {
				$parts[] = $this->style('option', '[options]');
				break;
			}
		}

		if ($command->getCommands()) {
			$parts[] = $this->style('command', $command->commandRequired ? '<command>' : '[command]');
		}

		foreach ($command->getArguments() as $argument) {
			if (!$argument->hidden) {
				$parts[] = $this->formatSyntax($argument, styled: true);
			}
		}

		return implode(' ', $parts);
	}


	/** @return list<string> */
	private function renderEntry(Flag|Option|Argument|Command $item, int $column, int $width): array
	{
		$syntax = $this->formatSyntax($item, styled: true);
		$plainWidth = Ansi::measure($syntax);
		$description = $this->describe($item);
		if (!$description) {
			return [self::Indent . $syntax];
		}

		if ($width - $column < self::MinDescriptionWidth) { // two columns do not fit, so the description goes below
			$indent = str_repeat(self::Indent, 2);
			$lines = $this->wrap($description, $width - strlen($indent));
			return [self::Indent . $syntax, ...array_map(fn($line) => $indent . $line, $lines)];
		}

		$lines = $this->wrap($description, $width - $column);
		$length = strlen(self::Indent) + $plainWidth;
		$out = $length > $column - 2
			? [self::Indent . $syntax] // too long to share the line; the description starts below
			: [self::Indent . $syntax . str_repeat(' ', $column - $length) . array_shift($lines)];

		foreach ($lines as $line) {
			$out[] = str_repeat(' ', $column) . $line;
		}

		return $out;
	}


	/**
	 * Builds the left column, e.g. "-o, --output <file>", "[input]..." or "check [paths...]".
	 */
	private function formatSyntax(Flag|Option|Argument|Command $item, bool $styled = false): string
	{
		$paint = fn(string $role, string $s) => $styled ? $this->style($role, $s) : $s;

		if ($item instanceof Command) {
			$arguments = [];
			foreach ($item->getArguments() as $argument) {
				if (!$argument->hidden) {
					$arguments[] = $this->formatSyntax($argument, $styled);
				}
			}

			return implode(' ', [$paint('command', (string) $item->name), ...$arguments]);

		} elseif ($item instanceof Argument) {
			$syntax = $item->optional ? "[$item->name]" : "<$item->name>";
			return $paint('argument', $syntax) . ($item->repeatable ? $paint('default', '...') : '');
		}

		$name = $item instanceof Flag && $item->negatable ? '--[no-]' . substr($item->name, 2) : $item->name;
		$syntax = $paint('option', $item->alias === null ? $name : "$item->alias, $name");
		if ($item instanceof Option) {
			$value = $item->valueName ?? ($item->enum === null ? 'value' : implode('|', $item->enum));
			$syntax .= $item->valueOptional
				? $paint('value', "[=$value]")
				: ' ' . $paint('value', "<$value>");
		}

		return $item->repeatable ? $syntax . $paint('default', '...') : $syntax;
	}


	/**
	 * The words of the description as it is printed, each with its role, including the "(default: x)" clause when
	 * one is due. Runs of whitespace collapse, since the description lives in a column.
	 * @return list<array{string, ?string}>
	 */
	private function describe(Parameter|Command $item): array
	{
		$words = [];
		foreach (preg_split('#\s+#', (string) $item->description, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
			$words[] = [$word, null];
		}

		$default = $item instanceof Parameter ? self::describeDefault($item) : null;
		if ($default !== null) {
			foreach (preg_split('#\s+#', "(default: $default)") as $word) {
				$words[] = [$word, 'default'];
			}
		}

		return $words;
	}


	/**
	 * Returns the default value in words: the description of the default when there is one, otherwise a scalar value
	 * or the value of an enum case, and null when there is nothing to show.
	 */
	private static function describeDefault(Parameter $parameter): ?string
	{
		$value = $parameter->default;
		$words = trim(match (true) {
			$parameter->defaultDescription !== null => $parameter->defaultDescription,
			$value instanceof \BackedEnum => (string) $value->value,
			is_scalar($value) && !is_bool($value) => (string) $value,
			default => '',
		});
		return $words === '' ? null : $words;
	}


	/**
	 * Breaks the words into lines of at most the given visible width and colors each run of one role. A word longer
	 * than the line is left to overflow rather than cut in half.
	 * @param  list<array{string, ?string}>  $words
	 * @return list<string>
	 */
	private function wrap(array $words, int $width): array
	{
		$lines = $line = [];
		$lineWidth = 0;
		foreach ($words as [$word, $role]) {
			$wordWidth = Ansi::measure($word);
			if ($line && $lineWidth + 1 + $wordWidth > $width) {
				$lines[] = $this->joinWords($line);
				$line = [];
			}

			$lineWidth = $line ? $lineWidth + 1 + $wordWidth : $wordWidth;
			$line[] = [$word, $role];
		}

		$lines[] = $this->joinWords($line);
		return $lines;
	}


	/**
	 * Joins the words of a line, a run of one role colored as a whole, so a color never spans two lines.
	 * @param  list<array{string, ?string}>  $words
	 */
	private function joinWords(array $words): string
	{
		$runs = [];
		foreach ($words as [$word, $role]) {
			$last = array_key_last($runs);
			if ($last !== null && $runs[$last][1] === $role) {
				$runs[$last][0] .= " $word";
			} else {
				$runs[] = [$word, $role];
			}
		}

		return implode(' ', array_map(fn(array $run) => $run[1] === null ? $run[0] : $this->style($run[1], $run[0]), $runs));
	}


	/**
	 * The column is as wide as the longest syntax that still fits into it; a longer one
	 * gets a line of its own and must not stretch the column for everybody else.
	 * @param  list<Flag|Option|Argument|Command|Section|Text>  $items
	 */
	private function syntaxWidth(array $items): int
	{
		$widths = [0];
		foreach ($items as $item) {
			if (
				($item instanceof Parameter || $item instanceof Command)
				&& ($width = Ansi::measure($this->formatSyntax($item))) <= self::MaxSyntaxWidth
			) {
				$widths[] = $width;
			}
		}

		return max($widths);
	}


	private function style(string $role, string $s): string
	{
		return $this->console ? $this->console->color(self::Theme[$role], $s) : $s;
	}
}
