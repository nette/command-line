<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine\Attributes;


/**
 * Describes the command a handler runs, for addCommandFrom() and Command::from(); addCommandFrom() derives the name
 * from the method, migrateSuppressions() is migrate-suppressions.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class Command
{
	public function __construct(
		public ?string $description = null,
		/** @var string|list<string>|null the usage line(s) written by hand; null generates them from the definitions */
		public string|array|null $usage = null,
		/** the command line has to name one of the commands below, unless a standalone flag answers */
		public bool $commandRequired = false,
		/** the name when it is not the one derived from the method; a closure has to give it */
		public ?string $name = null,
	) {
	}
}
