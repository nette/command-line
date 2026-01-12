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

$parser = new Parser('
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


Switches and Options
--------------------

**Switches** are flags without values, defined as `--verbose` or `-v, --verbose` in help text. They parse as `true` when present, `null` when absent.

**Options** accept values. Use `<value>` for required value, `[value]` for optional:

```
--output <file>   →  value required, --output alone throws exception
--format [type]   →  value optional, --format alone parses as true
```

The option itself is always optional - not using it returns null (or the fallback if `(default: x)` is specified).


Positional Arguments
--------------------

Positional arguments are values without dashes. They can't be defined in help text - use the second parameter instead:

```php
$parser = new Parser('
	-v, --verbose  Enable verbose mode
', [
	'file' => [],                          // required argument
	'output' => [Parser::Optional => true], // optional argument
]);
```

This accepts commands like `script.php input.txt` or `script.php -v input.txt output.txt`.

Arguments can appear anywhere on the command line - they don't have to come after options.


Additional Configuration
------------------------

Some settings can't be expressed in help text. Pass an array as the second parameter, keyed by option name:

```php
$parser = new Parser('
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


Error Handling
--------------

The parser throws `\Exception` for invalid input:

```php
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

Use `isEmpty()` to check if no command-line arguments were provided:

```php
if ($parser->isEmpty()) {
	$parser->help();
	exit;
}
```


Complete Example
================

Here's a practical script showing the typical usage pattern:

```php
#!/usr/bin/env php
<?php
use Nette\CommandLine\Parser;

require __DIR__ . '/vendor/autoload.php';

$parser = new Parser('
	-h, --help           Show this help
	-v, --verbose        Show detailed output
	-n, --dry-run        Show what would be done
	-f, --format [type]  Output format (default: json)
	-o, --output <file>  Output file
', [
	'--format' => [
		Parser::Enum => ['json', 'xml', 'csv'],
	],
	'input' => [
		Parser::RealPath => true,  // required positional argument
	],
]);

try {
	$args = $parser->parse();
} catch (\Exception $e) {
	fwrite(STDERR, "Error: {$e->getMessage()}\n");
	exit(1);
}

if ($parser->isEmpty() || $args['--help']) {
	echo "Usage: convert [options] <input>\n\n";
	$parser->help();
	exit;
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
