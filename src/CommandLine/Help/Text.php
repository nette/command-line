<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Help;


/**
 * A free paragraph of the help, rendered exactly as it is.
 * @internal
 */
final readonly class Text
{
	public function __construct(
		public string $text,
	) {
	}
}
