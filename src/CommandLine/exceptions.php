<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Parameters\Parameter;


/**
 * The command line is used wrongly. A handler throws it when the inputs conflict or do not fit the context; the
 * message writes code in backticks.
 */
class UsageException extends \Exception
{
	public function __construct(
		string $message,
		/** the command in which the command line went wrong */
		public readonly Command $command,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}
}


/**
 * Thrown when the command line does not match the definition (unknown option, missing argument,
 * value out of enum, ...). Errors in the definition itself are reported via \InvalidArgumentException.
 */
class ParseException extends UsageException
{
	public function __construct(
		string $message,
		Command $command,
		?\Throwable $previous = null,
		/** what was wrong */
		public readonly ParseFailure $reason = ParseFailure::InvalidValue,
		/** the parameter the error concerns, if any */
		public readonly ?Parameter $parameter = null,
		/** the part of the command line the error is about, as given, if any */
		public readonly ?string $token = null,
	) {
		parent::__construct($message, $command, $previous);
	}
}
