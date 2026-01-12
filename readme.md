Nette Command-Line
==================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/command-line.svg)](https://packagist.org/packages/nette/command-line)
[![Tests](https://github.com/nette/command-line/workflows/Tests/badge.svg?branch=master)](https://github.com/nette/command-line/actions)
[![Coverage Status](https://coveralls.io/repos/github/nette/command-line/badge.svg?branch=master)](https://coveralls.io/github/nette/command-line?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/command-line/v/stable)](https://github.com/nette/command-line/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/command-line/blob/master/license.md)

A lightweight library for building command-line applications in PHP. It provides:

- **Argument parsing** with switches, options, and positional arguments
- **Colorful terminal output** with ANSI support

Install it using Composer:

```
composer require nette/command-line
```

It requires PHP version 8.2 and supports PHP up to 8.5.

If you like Nette, **[please make a donation now](https://nette.org/donate)**. Thank you!


Parsing Command-Line Arguments
==============================

Every CLI script needs to handle arguments like `--verbose`, `-o output.txt`, or plain file names. The `Parser` class offers the fastest way to get started: just write your help text and let the parser extract option definitions from it:

```php
use Nette\CommandLine\Parser;

$parser = new Parser;
$parser->addFromHelp('
	-h, --help              Show this help
	-v, --verbose           Enable verbose mode
	-o, --output <file>     Output file
	-f, --format [type]     Output format (default: json)
	-I, --include <path>... Include paths
	--dry-run               Show what would be done
');

$args = $parser->parse();
```

That's it. The parser understands that `--verbose` is a switch, `--output` requires a value, `--format` has an optional value with `json` as fallback. Your help text stays in sync with actual option definitions.

The `parse()` method returns an associative array. Keys match option names exactly as defined, including the dashes:

```php
[
	'--help' => true,         // or null if not used
	'--verbose' => null,
	'--output' => 'file.txt', // or null if not used
	'--format' => 'json',     // fallback from (default: json)
	'--include' => ['src', 'lib'],
	'--dry-run' => null,
]
```

By default, `parse()` reads from `$_SERVER['argv']`. You can pass a custom array for testing:

```php
$args = $parser->parse(['--verbose', '-o', 'out.txt']);
```


Help Text Syntax
----------------

The parser extracts option definitions from formatted help text:

| Syntax | Meaning |
|--------|---------|
| `--verbose` | Switch (no value) |
| `-v, --verbose` | Switch with short alias |
| `--output <file>` | Option with required value |
| `--format [type]` | Option with optional value |
| `(default: json)` | Sets fallback value |
| `<path>...` | Repeatable option |

Each line defines one option. Option names must be separated from descriptions by at least two spaces.


Additional Configuration
------------------------

Some settings can't be expressed in help text. Pass an array as the second parameter, keyed by option name:

```php
$parser->addFromHelp('
	-c, --config <file>   Configuration file
	-I, --include <path>  Include path
	-n, --count <num>     Number of iterations
', [
	'--config' => [
		Parser::RealPath => true,
	],
	'--include' => [
		Parser::Repeatable => true,
	],
	'--count' => [
		Parser::Normalizer => fn($v) => (int) $v,
	],
]);
```

Available keys:

| Key | Description |
|-----|-------------|
| `Parser::Repeatable` | Collect multiple values into array |
| `Parser::RealPath` | Validate file exists and resolve to absolute path |
| `Parser::Normalizer` | Transform function `fn($value) => ...` |
| `Parser::Default` | Fallback value (same as `(default: x)` in help text) |
| `Parser::Enum` | Array of allowed values |


Fluent API
==========

When you need more control over option definitions, use the fluent API with `addSwitch()`, `addOption()`, and `addArgument()` methods. This approach gives you access to all features including normalizers, enums, and precise control over each parameter:

```php
use Nette\CommandLine\Parser;

$parser = new Parser;
$parser
	->addSwitch('--verbose', '-v')
	->addOption('--output', '-o')
	->addArgument('file');

$args = $parser->parse();
```

By default, `parse()` reads from `$_SERVER['argv']`. You can pass a custom array for testing:

```php
$args = $parser->parse(['--verbose', '-o', 'out.txt', 'input.txt']);
```


Switches, Options, and Arguments
--------------------------------

There are three types of command-line inputs:

**Switches** are flags without values, like `--verbose` or `-v`. They parse as `true` when present, `null` when absent:

```php
$parser->addSwitch('--verbose', '-v');
// --verbose  → true
// -v         → true
// (not used) → null
```

**Options** accept values, like `--output file.txt`. The value can be separated by space or `=`:

```php
$parser->addOption('--output', '-o');
// --output file.txt    → 'file.txt'
// --output=file.txt    → 'file.txt'
// -o file.txt          → 'file.txt'
// --output             → throws exception (value required)
// (not used)           → null
```

Note that the option itself is always optional - not using it returns null. However, when used, the value is required by default. Set `optionalValue: true` to allow the option without a value (parses as `true`):

```php
$parser->addOption('--format', '-f', optionalValue: true);
// --format json        → 'json'
// --format             → true
// (not used)           → null
```

When the same option is used multiple times without `repeatable: true`, the last value wins:

```php
$parser->addOption('--output', '-o');
// -o first.txt -o second.txt  → 'second.txt'
```

**Arguments** are positional values without dashes. By default they are required. Set `optional: true` to make them optional:

```php
$parser->addArgument('input');
// script.php file.txt  → 'file.txt'
// (not used)           → throws exception

$parser->addArgument('output', optional: true);
// (not used)           → null

$parser->addArgument('output', optional: true, fallback: 'out.txt');
// (not used)           → 'out.txt'
```

Use `fallback` to specify the value when an option or argument is not provided. For options with `optionalValue: true`, note that using the option without a value still parses as `true`, while the fallback is used only when the option is not present at all:

```php
$parser->addOption('--format', '-f', optionalValue: true, fallback: 'json');
// --format xml  → 'xml'
// --format      → true (option used without value)
// (not used)    → 'json' (fallback)
```

Arguments can appear anywhere on the command line - they don't have to come after options:

```php
// all of these are equivalent:
// script.php --verbose input.txt
// script.php input.txt --verbose
```


Restricting Values with Enum
----------------------------

Limit accepted values to a specific set:

```php
$parser->addOption('--format', '-f', enum: ['json', 'xml', 'csv']);
// --format yaml  → throws "Value of option --format must be json, or xml, or csv."
```


Repeatable Options
------------------

Set `repeatable: true` to collect multiple values into an array:

```php
$parser->addOption('--include', '-I', repeatable: true);
// -I src -I lib  → ['src', 'lib']
// (not used)     → []

$parser->addArgument('files', optional: true, repeatable: true);
// a.txt b.txt    → ['a.txt', 'b.txt']
```


Transforming Values
-------------------

Use `normalizer` to transform parsed values:

```php
$parser->addOption('--count', normalizer: fn($v) => (int) $v);
// --count 42  → 42 (integer)
```

For file path validation, use the built-in `normalizeRealPath`:

```php
$parser->addOption('--config', normalizer: Parser::normalizeRealPath(...));
// --config app.ini     → '/full/path/to/app.ini'
// --config missing.ini → throws "File path 'missing.ini' not found."
```


Mixing Both Approaches
----------------------

You can combine `addFromHelp()` with fluent methods when you need normalizers for some options:

```php
$parser
	->addFromHelp('
		-v, --verbose  Enable verbose mode
		-q, --quiet    Suppress output
	')
	->addOption('--config', '-c', normalizer: Parser::normalizeRealPath(...),
		description: 'Configuration file')
	->addArgument('input', description: 'Input file');
```


Error Handling
--------------

The parser throws `\Exception` for invalid input:

```php
use Nette\CommandLine\Parser;

$parser = new Parser;
$parser
	->addOption('--output', '-o')
	->addArgument('file');

try {
	$args = $parser->parse();
} catch (\Exception $e) {
	fwrite(STDERR, "Error: {$e->getMessage()}\n");
	exit(1);
}
```

Common error messages:

| Error | Cause |
|-------|-------|
| `Option --output requires argument.` | Option used without required value |
| `Unknown option --foo.` | Unrecognized option |
| `Missing required argument <file>.` | Required argument not provided |
| `Unexpected parameter foo.` | Extra positional argument |
| `Value of option --format must be json, or xml.` | Value not in enum |

Use `isEmpty()` to check if no command-line arguments were provided (i.e., user ran just `script.php` with nothing after it):

```php
if ($parser->isEmpty()) {
	$parser->help();
	exit;
}
```


Handling --help and --version
-----------------------------

When your script has required arguments, running `script.php --help` would normally fail because the required argument is missing. Use `parseOnly()` to check for info options first:

```php
$parser = new Parser;
$parser
	->addSwitch('--help', '-h')
	->addSwitch('--version', '-V')
	->addArgument('input');  // required

// First, check info options (no validation, no exceptions)
$info = $parser->parseOnly(['--help', '--version']);

if ($info['--help']) {
	$parser->help();
	exit;
}

if ($info['--version']) {
	echo "1.0.0\n";
	exit;
}

// Now do full parsing with validation
$args = $parser->parse();
```

The `parseOnly()` method:
- Parses only the specified options, ignoring everything else
- Respects aliases (`-h` → `--help`)
- Never throws exceptions
- Returns `null` for options that weren't used


Complete Example
================

Here's a real-world file converter script combining Parser and Console:

```php
#!/usr/bin/env php
<?php
use Nette\CommandLine\Parser;

require __DIR__ . '/vendor/autoload.php';

$parser = new Parser;
$parser
	->addFromHelp('
		-h, --help           Show this help
		-v, --verbose        Show detailed output
		-n, --dry-run        Show what would be done
		-f, --format [type]  Output format (default: json)
		-o, --output <file>  Output file
	', [
		'--format' => [
			Parser::Enum => ['json', 'xml', 'csv'],
		],
	])
	->addArgument('input', normalizer: Parser::normalizeRealPath(...));

// Handle --help before validation (avoids "missing argument" error)
if ($parser->isEmpty() || $parser->parseOnly(['--help'])['--help']) {
	echo "Usage: convert [options] <input>\n\n";
	$parser->help();
	exit;
}

try {
	$args = $parser->parse();
} catch (\Exception $e) {
	fwrite(STDERR, "Error: {$e->getMessage()}\n");
	exit(1);
}

if ($args['--verbose']) {
	echo "Converting {$args['input']} to {$args['--format']}...\n";
}

if ($args['--dry-run']) {
	echo "Dry run: No changes made.\n";
	exit;
}

// ... conversion logic here ...

echo "Done!\n";
```

The script accepts commands like:
- `convert input.txt` - convert with defaults
- `convert -v --format xml input.txt` - verbose, XML format
- `convert -o result.txt input.txt` - specify output file
- `convert --help` - show help (works even without input file)
