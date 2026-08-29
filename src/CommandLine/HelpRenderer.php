<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Help\Section;
use Nette\CommandLine\Parameters\Argument;
use Nette\CommandLine\Parameters\Flag;
use Nette\CommandLine\Parameters\Option;
use Nette\CommandLine\Parameters\Parameter;
use function strlen;


/**
 * Draws the help of a command. The colored output is the plain one wrapped in escape
 * sequences, so stripping them gives the plain one back.
 */
final class HelpRenderer
{
	/** role => color of Console::color(); every role the renderer draws with has to be here */
	private const Theme = [
		'heading' => 'yellow',
		'option' => 'lime',
		'argument' => 'lime',
		'command' => 'lime',
		'value' => 'aqua',
		'default' => 'gray',
	];

	private const MaxSyntaxWidth = 28;
	private const MinDescriptionWidth = 20;
	private const Indent = '  ';


	/**
	 * Without a console, render() writes to a new one on STDOUT and renderToString() returns plain text.
	 * Without a width the help fits the terminal.
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
				$block = array_merge($block, $this->renderEntry($item, $column, $width));
				continue;
			}

			$blocks[] = implode("\n", $block);
			$block = [$this->style('heading', $item->title . ':')];
		}

		$blocks[] = implode("\n", $block);
		$usage = $this->style('heading', 'Usage:') . ' ' . $this->generateUsage($command);
		$description = implode("\n", self::wrap($this->describe($command), $width));
		return implode("\n\n", array_filter([$usage, $description, ...$blocks], fn($s) => $s !== '')) . "\n";
	}


	/**
	 * The items of the command with headings, followed by the options it inherits from the commands above.
	 * @return list<Flag|Option|Argument|Command|Section>
	 */
	private static function collectItems(Command $command): array
	{
		$own = $command->getItems();

		$inherited = [];
		foreach (array_slice($command->getPath(), 0, -1) as $ancestor) {
			foreach ($ancestor->getOptions() as $option) {
				$inherited[] = $option;
			}
		}

		if (!$inherited) {
			return self::withHeadings($own);
		}

		$hasOwnOptions = (bool) $command->getOptions();
		return [...self::withHeadings($own), new Section($hasOwnOptions ? 'Global options' : 'Options'), ...$inherited];
	}


	/**
	 * Puts an "Options:", "Arguments:" or "Commands:" heading in front of every run of items of one kind.
	 * @param  list<Flag|Option|Argument|Command>  $items
	 * @return list<Flag|Option|Argument|Command|Section>
	 */
	private static function withHeadings(array $items): array
	{
		$out = [];
		$last = null;
		foreach ($items as $item) {
			$heading = match (true) {
				$item instanceof Flag, $item instanceof Option => 'Options',
				$item instanceof Argument => 'Arguments',
				default => 'Commands',
			};
			if ($heading !== $last) {
				$last = $heading;
				$out[] = new Section($heading);
			}

			$out[] = $item;
		}

		return $out;
	}


	private function generateUsage(Command $command): string
	{
		$root = $command->getRoot();
		$parts = [$root->name ?? basename((string) ($_SERVER['argv'][0] ?? 'program'))];
		if ($command !== $root) {
			$parts[] = $command->getFullName();
		}

		foreach ($command->getPath() as $level) {
			if ($level->getOptions()) {
				$parts[] = '[options]';
				break;
			}
		}

		if ($command->getCommands()) {
			$parts[] = $command->commandRequired ? '<command>' : '[command]';
		}

		foreach ($command->getArguments() as $argument) {
			$parts[] = $this->formatSyntax($argument);
		}

		return implode(' ', $parts);
	}


	/** @return list<string> */
	private function renderEntry(Flag|Option|Argument|Command $item, int $column, int $width): array
	{
		$syntax = $this->formatSyntax($item, styled: true);
		$plainWidth = Ansi::measure($syntax);
		$description = $this->describe($item);
		if ($description === '') {
			return [self::Indent . $syntax];
		}

		if ($width - $column < self::MinDescriptionWidth) { // two columns do not fit, so the description goes below
			$indent = str_repeat(self::Indent, 2);
			$lines = self::wrap($description, $width - strlen($indent));
			return [self::Indent . $syntax, ...array_map(fn($line) => $indent . $line, $lines)];
		}

		$lines = self::wrap($description, $width - $column);
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
				$arguments[] = $this->formatSyntax($argument, $styled);
			}

			return implode(' ', [$paint('command', (string) $item->name), ...$arguments]);

		} elseif ($item instanceof Argument) {
			$syntax = $item->optional ? "[$item->name]" : "<$item->name>";
			return $paint('argument', $item->repeatable ? "$syntax..." : $syntax);
		}

		$name = $item instanceof Flag && $item->negatable ? '--[no-]' . substr($item->name, 2) : $item->name;
		$syntax = $paint('option', $item->alias === null ? $name : "$item->alias, $name");
		if ($item instanceof Option) {
			$value = $item->enum === null ? 'value' : implode('|', $item->enum);
			$syntax .= $item->valueOptional
				? $paint('value', "[=$value]")
				: ' ' . $paint('value', "<$value>");
		}

		return $item->repeatable ? $syntax . $paint('option', '...') : $syntax;
	}


	/**
	 * The description as it is printed, including the "(default: x)" clause when one is due.
	 */
	private function describe(Parameter|Command $item): string
	{
		// runs of whitespace collapse: the description lives in a column
		$description = trim((string) preg_replace('#\s+#', ' ', (string) $item->description));
		$default = $item instanceof Parameter ? $item->describeDefault() : null;
		if ($default !== null) {
			$description = trim("$description (default: $default)");
		}

		return (string) preg_replace_callback(
			'#\(default: [^)]*\)#',
			fn($m) => $this->style('default', $m[0]),
			$description,
		);
	}


	/**
	 * Breaks the text into lines of at most the given visible width. A word longer than the line
	 * is left to overflow rather than cut in half.
	 * @return list<string>
	 */
	private static function wrap(string $text, int $width): array
	{
		$lines = [];
		$line = '';
		foreach (preg_split('#\s+#', $text) ?: [] as $word) {
			if ($line === '') {
				$line = $word;
			} elseif (Ansi::measure($line) + 1 + Ansi::measure($word) <= $width) {
				$line .= ' ' . $word;
			} else {
				$lines[] = $line;
				$line = $word;
			}
		}

		$lines[] = $line;
		return $lines;
	}


	/**
	 * The column is as wide as the longest syntax that still fits into it; a longer one
	 * gets a line of its own and must not stretch the column for everybody else.
	 * @param  list<Flag|Option|Argument|Command|Section>  $items
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
