<?php

use Tester\Assert,
	Nette\CommandLine\Parser;

require __DIR__ . '/bootstrap.php';



test(function() {
	$cmd = new Parser('
		-p
	');

	Assert::same( array('-p' => NULL), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );

	$cmd = new Parser('
		-p  description
	');

	Assert::same( array('-p' => NULL), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );
});


test(function() { // default value
	$cmd = new Parser('
		-p  (default: 123)
	');

	Assert::same( array('-p' => '123'), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );


	$cmd = new Parser('
		-p
	', array(
		'-p' => array(Parser::VALUE => 123),
	));

	Assert::same( array('-p' => 123), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );
});


test(function() { // alias
	$cmd = new Parser('
		-p | --param
	');

	Assert::same( array('--param' => NULL), $cmd->parse(array()) );
	Assert::same( array('--param' => TRUE), $cmd->parse(array('-p')) );
	Assert::same( array('--param' => TRUE), $cmd->parse(array('--param')) );
	Assert::same( array('--param' => TRUE), $cmd->parse(explode(' ', '-p --param')) );
	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('-p=val'));
	}, 'Exception', 'Option --param has not argument.');

	$cmd = new Parser('
		-p --param
	');

	Assert::same( array('--param' => TRUE), $cmd->parse(array('-p')) );

	$cmd = new Parser('
		-p, --param
	');

	Assert::same( array('--param' => TRUE), $cmd->parse(array('-p')) );
});


test(function() { // argument
	$cmd = new Parser('
		-p param
	');

	Assert::same( array('-p' => NULL), $cmd->parse(array()) );
	Assert::same( array('-p' => 'val'), $cmd->parse(explode(' ', '-p val')) );
	Assert::same( array('-p' => 'val'), $cmd->parse(explode(' ', '-p=val')) );
	Assert::same( array('-p' => 'val2'), $cmd->parse(explode(' ', '-p val1 -p val2')) );

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('-p'));
	}, 'Exception', 'Option -p requires argument.');

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('-p', '-a'));
	}, 'Exception', 'Option -p requires argument.');


	$cmd = new Parser('
		-p=<param>
	');

	Assert::same( array('-p' => 'val'), $cmd->parse(explode(' ', '-p val')) );
});



test(function() { // optional argument
	$cmd = new Parser('
		-p [param]
	');

	Assert::same( array('-p' => NULL), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );
	Assert::same( array('-p' => 'val'), $cmd->parse(explode(' ', '-p val')) );


	$cmd = new Parser('
		-p param
	', array(
		'-p' => array(Parser::VALUE => 123),
	));

	Assert::same( array('-p' => 123), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );
	Assert::same( array('-p' => 'val'), $cmd->parse(explode(' ', '-p val')) );


	$cmd = new Parser('
		-p param
	', array(
		'-p' => array(Parser::OPTIONAL => TRUE),
	));

	Assert::same( array('-p' => NULL), $cmd->parse(array()) );
	Assert::same( array('-p' => TRUE), $cmd->parse(array('-p')) );
	Assert::same( array('-p' => 'val'), $cmd->parse(explode(' ', '-p val')) );
});



test(function() { // repeatable argument
	$cmd = new Parser('
		-p [param]...
	');

	Assert::same( array('-p' => array()), $cmd->parse(array()) );
	Assert::same( array('-p' => array(TRUE)), $cmd->parse(array('-p')) );
	Assert::same( array('-p' => array('val')), $cmd->parse(explode(' ', '-p val')) );
	Assert::same( array('-p' => array('val1', 'val2')), $cmd->parse(explode(' ', '-p val1 -p val2')) );
});



test(function() { // realpath
	$cmd = new Parser('
		-p <path>
	', array(
		'-p' => array(Parser::REALPATH => TRUE),
	));

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('-p', 'xyz'));
	}, 'Exception', "File path 'xyz' not found.");
	Assert::same( array('-p' => __FILE__), $cmd->parse(array('-p', __FILE__)) );
});



test(function() { // positional arguments
	$cmd = new Parser('', array(
		'pos' => array(),
	));

	Assert::same( array('pos' => 'val'), $cmd->parse(array('val')) );

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array());
	}, 'Exception', 'Missing required argument <pos>.');

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('val1', 'val2'));
	}, 'Exception', 'Unexpected parameter val2.');

	$cmd = new Parser('', array(
		'pos' => array(Parser::REPEATABLE => TRUE),
	));

	Assert::same( array('pos' => array('val1', 'val2')), $cmd->parse(array('val1', 'val2')) );


	$cmd = new Parser('', array(
		'pos' => array(Parser::OPTIONAL => TRUE),
	));

	Assert::same( array('pos' => NULL), $cmd->parse(array()) );


	$cmd = new Parser('', array(
		'pos' => array(Parser::VALUE => 'default', Parser::REPEATABLE => TRUE),
	));

	Assert::same( array('pos' => array('default')), $cmd->parse(array()) );
});



test(function() { // errors
	$cmd = new Parser('
		-p
	');

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('-x'));
	}, 'Exception', 'Unknown option -x.');

	Assert::exception(function() use ($cmd) {
		$cmd->parse(array('val'));
	}, 'Exception', 'Unexpected parameter val.');
});
