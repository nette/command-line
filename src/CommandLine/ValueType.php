<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\CommandLine;


/**
 * Describes value requirement for command-line options and arguments.
 */
enum ValueType
{
	case None;      // --foo (no value, switch)
	case Required;  // <file>, --foo <file> (value required)
	case Optional;  // [file], --foo [file] (value optional)
}
