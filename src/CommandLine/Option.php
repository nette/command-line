<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\CommandLine;


/**
 * Configuration for a command-line option or positional argument.
 */
final readonly class Option
{
	public bool $positional;


	public function __construct(
		/** Option name (e.g. '--foo') or argument name (e.g. 'foo') */
		public string $name,
		public ValueType $type = ValueType::Required,
		/** Short alias (e.g. '-f') */
		public ?string $alias = null,
		/** Parsed value when option/argument is not provided */
		public mixed $fallback = null,
		/** Can be specified multiple times (collects into array) */
		public bool $repeatable = false,
		/** Allowed values */
		public ?array $enum = null,
		/** Transform function applied to the value */
		public ?\Closure $normalizer = null,
	) {
		$this->positional = !str_starts_with($name, '-');
	}
}
