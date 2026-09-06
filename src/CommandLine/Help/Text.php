<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Help;


/**
 * A text of the help, printed as it is: not wrapped, collapsed or colored.
 * @internal
 */
final readonly class Text
{
	public function __construct(
		public string $text,
	) {
	}
}
