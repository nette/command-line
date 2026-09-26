<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use function is_int, is_string;
use const PHP_SAPI;


/**
 * Asks the user at a terminal: writes the question to the console and reads the answer from the input. It asks only
 * when both are a terminal, so an answer never comes from a pipe nobody watches.
 */
final class Prompt
{
	/** @var resource */
	private $input;
	private readonly bool $inputTerminal;


	/**
	 * @param  ?resource  $input  where the answers come from, STDIN by default
	 * @param  ?bool  $inputTerminal  null asks the input; a test over a memory stream passes true
	 */
	public function __construct(
		private readonly Console $console = new Console,
		$input = null,
		?bool $inputTerminal = null,
	) {
		$this->input = $input ?? STDIN;
		if (!preg_match('#[r+]#', stream_get_meta_data($this->input)['mode'])) {
			throw new \InvalidArgumentException('The stream is not open for reading.');
		}

		$this->inputTerminal = $inputTerminal
			?? ((PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') && @stream_isatty($this->input)); // @ may trigger error 'cannot cast a filtered stream on this system'
	}


	/**
	 * Tells whether a user answers at a terminal, so that the application can ask; without one, the prompt refuses.
	 */
	public function isInteractive(): bool
	{
		return $this->inputTerminal && $this->console->isTerminal();
	}


	/**
	 * Asks the question until the answer is one of the choices, in any case, and returns the choice as declared. An
	 * empty answer is the default, without one it asks again; the end of the input is null. The choices are a list,
	 * such as ['y', 'n'], shown as [y/N] after the question, or a map of a choice to its description, each written as
	 * a line of a menu above the question.
	 * @param  list<string>|array<string, string>  $choices
	 * @throws \LogicException  when the prompt is not interactive
	 */
	public function choose(string $question, array $choices, ?string $default = null): ?string
	{
		$menu = array_is_list($choices) ? [] : $choices;
		$keys = self::checkChoices($menu ? array_keys($menu) : $choices, $default);
		if (!$this->isInteractive()) {
			throw new \LogicException('The prompt has no user to ask, so it asks only when isInteractive() is true.');
		}

		// only the default is in capitals, so that no other choice looks like it
		$prompt = $question
			. ' ['
			. implode('/', array_map(fn($choice) => $choice === $default ? strtoupper($choice) : strtolower($choice), $keys))
			. '] ';
		$text = '';
		foreach ($menu as $choice => $description) {
			$text .= "  [$choice] $description\n";
		}

		$text .= $prompt;
		while (true) {
			$this->console->write($text);
			$text = $prompt; // the menu is not repeated
			$line = fgets($this->input);
			if ($line === false) {
				$this->console->writeLine();
				return null;
			}

			$this->console->markLineStart();
			$answer = strtolower(trim($line));
			if ($answer === '' && $default !== null) {
				return $default;
			}

			foreach ($keys as $choice) {
				if (strtolower($choice) === $answer) {
					return $choice;
				}
			}
		}
	}


	/**
	 * @param  list<mixed>  $choices
	 * @return list<string>
	 */
	private static function checkChoices(array $choices, ?string $default): array
	{
		if (!$choices) {
			throw new \InvalidArgumentException('There are no choices.');
		}

		$list = $seen = [];
		foreach ($choices as $choice) {
			if (is_int($choice)) {
				throw new \InvalidArgumentException("Choice $choice is a number, but a choice is a string.");
			} elseif (!is_string($choice) || $choice === '' || trim($choice) !== $choice) {
				throw new \InvalidArgumentException('A choice must be a string with no surrounding whitespace.');
			} elseif (isset($seen[strtolower($choice)])) {
				throw new \InvalidArgumentException("Choice '$choice' is given twice, the case aside.");
			}

			$seen[strtolower($choice)] = true;
			$list[] = $choice;
		}

		if ($default !== null && !in_array($default, $list, true)) {
			throw new \InvalidArgumentException("Default '$default' is not one of the choices.");
		}

		return $list;
	}
}
