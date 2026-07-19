<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * What was wrong with the command line.
 */
enum ParseError
{
	case UnknownOption;
	case UnknownCommand;
	case MissingValue;
	case UnexpectedValue;
	case MissingArgument;
	case UnexpectedArgument;
	case InvalidValue;
	case CommandMismatch;
}
