<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * How many colors a stream takes; the values are the levels of FORCE_COLOR.
 */
enum ColorDepth: int
{
	case None = 0;
	case Ansi16 = 1;
	case Ansi256 = 2;
	case TrueColor = 3;
}


/**
 * What was wrong with the command line.
 */
enum ParseFailure
{
	case UnknownOption;
	case UnknownCommand;
	case MissingCommand;
	case MissingValue;
	case UnexpectedValue;
	case MissingArgument;
	case UnexpectedArgument;
	case InvalidValue;
	case CommandMismatch;
}
