<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * The command line is used wrongly; the message writes code in backticks.
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
