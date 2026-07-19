<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;

use Nette\CommandLine\Parameters\Parameter;


/**
 * Thrown when the command line does not match the definition (unknown option, missing argument,
 * value out of enum, ...). Errors in the definition itself are reported via \InvalidArgumentException.
 */
class ParseException extends \Exception
{
	public function __construct(
		string $message,
		/** the command in which the command line went wrong */
		public readonly Command $command,
		?\Throwable $previous = null,
		/** what was wrong */
		public readonly ParseError $reason = ParseError::InvalidValue,
		/** the parameter the error concerns, if any */
		public readonly ?Parameter $parameter = null,
	) {
		parent::__construct($message, 0, $previous);
	}
}
