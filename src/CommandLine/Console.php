<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\CommandLine;


/**
 * Stupid console writer.
 */
class Console
{
	/** @var bool */
	private $useColors;


	public function __construct()
	{
		$this->useColors = PHP_SAPI === 'cli' && ((function_exists('posix_isatty') && posix_isatty(STDOUT))
			|| getenv('ConEmuANSI') === 'ON' || getenv('ANSICON') !== FALSE || getenv('term') === 'xterm-256color');
	}


	public function useColors($state = TRUE)
	{
		$this->useColors = (bool) $state;
	}


	public function color($color = NULL, $s = NULL)
	{
		static $colors = [
			'black' => '0;30', 'gray' => '1;30', 'silver' => '0;37', 'white' => '1;37',
			'navy' => '0;34', 'blue' => '1;34', 'green' => '0;32', 'lime' => '1;32',
			'teal' => '0;36', 'aqua' => '1;36', 'maroon' => '0;31', 'red' => '1;31',
			'purple' => '0;35', 'fuchsia' => '1;35', 'olive' => '0;33', 'yellow' => '1;33',
			NULL => '0',
		];
		if ($this->useColors) {
			$c = explode('/', $color);
			return "\033["
				. ($c[0] ? $colors[$c[0]] : '')
				. (empty($c[1]) ? '' : ';4' . substr($colors[$c[1]], -1))
				. 'm' . $s
				. ($s === NULL ? '' : "\033[0m");
		}
		return $s;
	}
}
