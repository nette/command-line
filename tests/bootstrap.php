<?php declare(strict_types=1);

if (@!include __DIR__ . '/../vendor/autoload.php') { // @ dependencies may not be installed
	echo 'Install Nette Tester using `composer install`';
	exit(1);
}

// configure environment
Tester\Environment::setup();
Tester\Environment::setupFunctions();

if (!defined('STDOUT')) { // php-cgi has no standard streams
	define('STDIN', fopen('php://stdin', 'r'));
	define('STDOUT', fopen('php://stdout', 'w'));
	define('STDERR', fopen('php://stderr', 'w'));
}
