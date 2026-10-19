<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * Runs the program in the process: answers --help and --version, reports a mistake of the command line with the help
 * of the command it concerns and runs the handler of the command the line selects, returning the exit code.
 */
final readonly class Runner
{
	private Console $out;
	private Console $err;


	/**
	 * Adds to the program the flags the runner answers: --help, --version where a version is given, --no-color where
	 * asked for. A flag of that name the program has already is answered as it is, an alias it takes is left out.
	 */
	public function __construct(
		private Command $program,
		/**
		 * @var string|(\Closure(Console): string)|null the name and the version, printed by --version and above
		 * the help; a closure is given the console it is written to, so that it is colored as that console is
		 */
		private string|\Closure|null $version = null,
		/** @var bool adds --no-color, which turns the colors of both consoles off */
		private bool $colorFlag = false,
		/** @var bool a command line without any token prints the help instead of running the program */
		private bool $helpWhenEmpty = false,
		/** @var int the exit code of a mistake of the command line */
		private int $usageExitCode = 2,
		/**
		 * @var ?int an exception the handler throws is reported by its message with this exit code; null
		 * lets it pass, and an Error, which is a bug, passes always
		 */
		private ?int $errorExitCode = null,
		?Console $out = null,
		?Console $err = null,
	) {
		if ($program->getParent() !== null) {
			throw new \InvalidArgumentException("The runner runs the program, not command '{$program->getFullName()}'.");
		}

		$this->out = $out ?? new Console;
		$this->err = $err ?? new Console(STDERR);
		self::addFlag($program, '--help', '-h', 'Print this help', standalone: true);
		if ($version !== null) {
			self::addFlag($program, '--version', '-V', 'Print the name and the version', standalone: true);
		}

		if ($colorFlag) {
			self::addFlag($program, '--no-color', null, 'Print plain output without colors');
		}
	}


	/**
	 * Runs the command line, the one of the running process unless given, and returns the exit code.
	 * @param  ?list<string>  $argv  the tokens without the name of the program
	 */
	public function run(?array $argv = null): int
	{
		$argv ??= array_values(array_map(strval(...), array_slice((array) ($_SERVER['argv'] ?? []), 1)));
		$end = array_search('--', $argv, strict: true);
		// read before the line is parsed, so that a mistake of the line is reported without colors too
		if ($this->colorFlag && in_array('--no-color', $end === false ? $argv : array_slice($argv, 0, $end), true)) {
			$this->out->setColorDepth(ColorDepth::None);
			$this->err->setColorDepth(ColorDepth::None);
		}

		if ($this->helpWhenEmpty && $argv === []) { // before parsing, which would demand the required arguments
			$this->printHelp($this->program);
			return 0;
		}

		try {
			$args = (new Parser)->parse($this->program, $argv);
			if ($args->get('--help')) {
				$this->printHelp($args->command);
				return 0;
			} elseif ($this->version !== null && $args->get('--version')) {
				$this->out->writeLine($this->formatVersion($this->version));
				return 0;
			}

			return $this->runHandler($args);

		} catch (UsageException $e) {
			(new HelpRenderer($this->err))->renderError($e);
			return $this->usageExitCode;
		}
	}


	/** @throws UsageException */
	private function runHandler(ParseResult $args): int
	{
		try {
			return $args->run();
		} catch (UsageException $e) {
			throw $e;
		} catch (\Exception $e) { // dresscode:ignore mistakes.exceptionWhereThrowableBelongs -- an Error is a bug, not a failure to report
			if ($this->errorExitCode === null) {
				throw $e;
			}

			$this->err->writeLine($this->err->color('red', 'Error:') . ' ' . (new HelpRenderer($this->err))->formatText($e->getMessage()));
			return $this->errorExitCode;
		}
	}


	private function printHelp(Command $command): void
	{
		if ($this->version !== null) {
			$this->out->writeLine($this->formatVersion($this->version) . "\n");
		}

		(new HelpRenderer($this->out))->render($command);
	}


	/** @param  string|(\Closure(Console): string)  $version */
	private function formatVersion(string|\Closure $version): string
	{
		return $version instanceof \Closure ? $version($this->out) : $version;
	}


	private static function addFlag(Command $program, string $name, ?string $alias, string $description, bool $standalone = false): void
	{
		if (!self::isNameTaken($program, $name)) {
			$program->moveBeforeCommands($program->addFlag(
				$name,
				$description,
				alias: $alias !== null && !self::isNameTaken($program, $alias) ? $alias : null,
				standalone: $standalone,
			));
		}
	}


	/** Whether an option of the command or of a command below it has the name, which an option of the program then cannot. */
	private static function isNameTaken(Command $command, string $name): bool
	{
		foreach ($command->getOptions() as $option) {
			if ($option->hasName($name)) {
				return true;
			}
		}

		foreach ($command->getCommands() as $subcommand) {
			if (self::isNameTaken($subcommand, $name)) {
				return true;
			}
		}

		return false;
	}
}
