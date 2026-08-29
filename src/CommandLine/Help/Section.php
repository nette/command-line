<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Help;


/**
 * A heading in the help, e.g. "Options:". The colon is added when it is rendered.
 * @internal
 */
final readonly class Section
{
	public string $title;


	public function __construct(string $title)
	{
		$this->title = rtrim($title, ':');
	}
}
