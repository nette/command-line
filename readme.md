Nette Command-Line
==================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/command-line.svg)](https://packagist.org/packages/nette/command-line)
[![Tests](https://github.com/nette/command-line/workflows/Tests/badge.svg?branch=master)](https://github.com/nette/command-line/actions)
[![Coverage Status](https://coveralls.io/repos/github/nette/command-line/badge.svg?branch=master)](https://coveralls.io/github/nette/command-line?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/command-line/v/stable)](https://github.com/nette/command-line/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/command-line/blob/master/license.md)

A lightweight library for building command-line applications in PHP. It provides:

- **Argument parsing** with flags, options, positional arguments and commands
- **Colorful terminal output** with ANSI support

Install it using Composer:

```
composer require nette/command-line
```

It requires PHP version 8.2 and supports PHP up to 8.5.

If you like Nette, **[please make a donation now](https://nette.org/donate)**. Thank you!


Parsing Command-Line Arguments
==============================

Every CLI script needs to handle arguments like `--verbose`, `-o output.txt`, or plain file names. The library splits the job into three classes, each doing one thing:

- `Command` says what the script accepts,
- `Parser` reads the command line according to it,
- `Console` writes to the terminal.

```php
use Nette\CommandLine\Command;
use Nette\CommandLine\Parser;

$command = new Command('convert');
$command->addFlag('--verbose', 'Enable verbose mode', alias: '-v');
$command->addOption('--output', 'Output file', alias: '-o');
$command->addArgument('input', 'File to convert');

$args = (new Parser)->parse($command);
```

Each `add*()` method takes the name, the description and then everything else about the parameter as named arguments, and returns the parameter it created. Every setting can be read back as a property, such as `$option->alias`, but a parameter cannot be changed afterwards, so it is checked as soon as it is added. To reach a parameter again later, call `$command->getParameter('--output', Option::class)`; the class, from the `Nette\CommandLine\Parameters` namespace, checks the kind of the parameter and tells static analysis which properties it has.

The `parse()` method returns a `Result`, which you read like an array. Keys match the names exactly as defined, including the dashes:

```php
$args['--verbose'];  // true, or null if not used
$args['--output'];   // 'file.txt', or null if not used
$args['input'];      // 'photo.png'
```

Asking for a name that was never defined throws an exception, so a typo cannot pass for a value that was just not given. `isset()` answers as it does on an array: `false` both for an unknown name and for `null`. `toArray()` gives you all the values at once.

By default, `parse()` reads `$_SERVER['argv']`. Pass the tokens yourself, without the name of the script, which is handy for testing:

```php
$args = (new Parser)->parse($command, ['--verbose', '-o', 'out.txt', 'photo.png']);
```

`$args->isEmpty()` tells you that nothing at all was given on the command line. `$args->wasProvided('--output')` tells whether one parameter was given, so a value equal to the default can be told from the default itself, which matters when you merge the command line with a configuration file.


Flags, Options, and Arguments
-----------------------------

There are three types of command-line inputs.

**Flags** are named inputs without values, like `--verbose` or `-v`. They parse as `true` when present, `null` when absent:

```php
$command->addFlag('--verbose', alias: '-v');
// --verbose  → true
// -v         → true
// (not used) → null
```

**Options** accept values, like `--output file.txt`. The value can be separated by a space or by `=`:

```php
$command->addOption('--output', alias: '-o');
// --output file.txt    → 'file.txt'
// --output=file.txt    → 'file.txt'
// -o file.txt          → 'file.txt'
// --output             → throws (the value is required)
// (not used)           → null
```

The option itself is always optional, but when it is used, its value is required. Pass `valueOptional: true` to allow the option without a value; it then parses as `true`. Such a value can be given only attached with `=`, so the next token is never taken for it:

```php
$command->addOption('--format', valueOptional: true);
// --format=json  → 'json'
// --format       → true
// --format json  → true, and json is an argument
// (not used)     → null
```

When the same option is used several times, the last value wins, unless it is repeatable. The earlier values are neither checked nor converted.

**Arguments** are positional values without dashes. They are required unless you pass `optional: true`:

```php
$command->addArgument('input');
// file.txt    → 'file.txt'
// (not used)  → throws

$command->addArgument('output', optional: true);
// (not used)  → null
```

Arguments can appear anywhere on the command line, before or after the options. Everything after a lone `--` is positional even if it starts with a dash, and a lone `-` is always a value (by convention it means stdin or stdout):

```php
// script.php -- --weird  → input = '--weird'
// script.php -           → input = '-'
```

Several single-letter flags can be written as one token: `-va` means `-v -a`, and the last letter may take a value, so `-vao out.txt` means `-v -a -o out.txt`.

A flag takes no value, so `addFlag()` has none of the settings of a value. `enum` and `normalizer` exist on `addOption()` and `addArgument()`, `valueOptional` on `addOption()` only.


Default Values
--------------

`default` says what an option or an argument is worth when it is **not** on the command line. It is independent of `valueOptional`, which says what happens when the option **is** there without a value:

```php
$command->addOption('--format', default: 'json');
// --format xml  → 'xml'
// --format      → throws (the value is required)
// (not used)    → 'json'

$command->addOption('--format', valueOptional: true, default: 'json');
// --format=xml  → 'xml'
// --format      → true
// (not used)    → 'json'
```

The default value is returned exactly as you gave it: it is not normalized or checked against the enum. A required argument with a default value makes no sense and is refused; mark such an argument optional.


Restricting Values
------------------

`enum` limits the accepted values to a list:

```php
$command->addOption('--format', enum: ['json', 'xml', 'csv']);
// --format yaml  → throws "Option --format: expects json, xml or csv, 'yaml' given."
```


Repeatable Parameters
---------------------

Pass `repeatable: true` to collect all the values into an array:

```php
$command->addOption('--include', alias: '-I', repeatable: true);
// -I src -I lib  → ['src', 'lib']
// (not used)     → []

$command->addArgument('files', optional: true, repeatable: true);
// a.txt b.txt    → ['a.txt', 'b.txt']
```

A repeatable argument takes all the remaining values, so it has to be the last one.


Transforming Values
-------------------

A normalizer transforms the value read from the command line. It is any closure, such as `fn($v) => strtolower($v)`:

```php
$command->addOption('--format', normalizer: fn($v) => strtolower($v));
// --format JSON  → 'json'
```

It runs only on values that really come from the command line: not on the default value, and not on the `true` of an option used without its optional value. A normalizer reports a bad value by throwing an exception. The parser turns it into a `ParseException` naming the parameter, so the user reads `Option --format:` followed by the message of the exception instead of a stack trace. An `\Error` is not caught, because a broken normalizer is a bug, not a bad value.


Validated Values
----------------

When you want validated values, hand the result to [nette/schema](https://github.com/nette/schema) as a second step. The library itself stays dependency-free. The parser returns the strings as they were written and `null` for what was not used, so leave the `null` values out to let the defaults of the schema apply, and let the schema check and cast the strings:

```php
use Nette\Schema\Expect;
use Nette\Schema\Processor;

$options = (new Processor)->process(Expect::structure([
	'--jobs' => Expect::string()->pattern('[1-9][0-9]*')->castTo('int')->default(8),
	'paths' => Expect::listOf('string'),
])->otherItems(), array_filter($args->toArray(), fn($value) => $value !== null));
```


Commands
========

Tools like `git` or `composer` have commands: `git commit`, `composer install`. Each command has its own options and arguments. You create one with `addCommand()`, which returns a new `Command` to be set up like the program itself:

```php
$cli = new Command('dresscode');
$cli->addOption('--config', 'Configuration file', alias: '-c');

$check = $cli->addCommand('check', 'Report violations');
$check->addArgument('paths', 'Files or directories', optional: true, repeatable: true);
$check->addFlag('--generate-baseline', 'Write the violations into the baseline');

$explain = $cli->addCommand('explain', 'What a rule is for');
$explain->addArgument('rule', 'Name of the rule');

$result = (new Parser)->parse($cli);
```

The first positional token picks the command, and `$result->command` tells you which one it was. When the line names no command, it is the program itself. The natural way to dispatch is `match`:

```php
exit(match ($result->command) {
	$check => $app->check($result['paths'], $result['--generate-baseline']),
	$explain => $app->explain($result['rule']),
	$cli => $app->usage(),
});
```

Without a `default` arm, `match` throws `UnhandledMatchError` when you forget a command.

A command accepts its own options and those of all the commands above it, so `--config` works both in `dresscode -c x.neon check` and in `dresscode check -c x.neon`. The result holds only the parameters on the way to the selected command. An option of a different command is refused with a message saying where it belongs: `Option --generate-baseline belongs to command 'check'.`

Commands can be nested as deep as you need, `git remote add` is just a command of a command. A command that has subcommands cannot take arguments, and two commands side by side may use the same option names.

To test a single command, pass it to the parser with the whole line. The line is always read from the root, and the parser checks that it really runs the command you gave:

```php
$result = (new Parser)->parse($check, ['check', 'src', '--generate-baseline']);
```


Error Handling
==============

The parser throws `Nette\CommandLine\ParseException` for invalid user input. Its `command` property is the command in which the input went wrong, so you can show the right help:

```php
use Nette\CommandLine\ParseException;

try {
	$result = (new Parser)->parse($cli);
} catch (ParseException $e) {
	fwrite(STDERR, "Error: {$e->getMessage()}\n");
	exit(2);
}
```

Its `reason` tells what was wrong, as a case of the `ParseError` enum, such as `ParseError::UnknownOption` or `ParseError::MissingArgument`, and `parameter` is the parameter the error concerns, when there is one. An application can word the messages itself from these two.

Common error messages:

| Error | Cause |
|-------|-------|
| `Option --output requires a value.` | Option used without its required value |
| `Option --verbose does not accept a value.` | A value given to a flag |
| `Unknown option --verbos. Did you mean --verbose?` | Unrecognized option |
| `Missing required argument <file>.` | Required argument not provided |
| `Unexpected arguments b, c.` | Extra positional arguments |
| `Option --format: expects json or xml, 'yaml' given.` | Value not in the enum |
| `Unknown command 'chekc'. Did you mean 'check'?` | Unrecognized command |
| `Option --generate-baseline belongs to command 'check'.` | Option of another command |

Errors in the definition itself, such as an option name without a dash, a name used twice or a required argument after an optional one, are programming errors and throw `\InvalidArgumentException` or `\LogicException`.


Colorful Output
===============

`Console` wraps text in ANSI color codes so your output stands out:

```php
$console = new Console;
echo $console->color('red', 'Error!') . "\n";
echo $console->color('white/blue', 'White text on blue background') . "\n";
```

The color is `'foreground'` or `'foreground/background'`, one of `black`, `gray`, `silver`, `white`, `navy`, `blue`, `green`, `lime`, `teal`, `aqua`, `maroon`, `red`, `purple`, `fuchsia`, `olive` and `yellow`. Colors are used only when the output supports them (`useColors()` overrides that); otherwise `color()` returns the plain string.

`Console::detectColors()` honors the [NO_COLOR](https://no-color.org) and `FORCE_COLOR` environment variables. `Console::detectTerminal()` tells you whether the output is an interactive terminal, which is the right check for progress bars and prompts: a user may turn colors off and still sit at a real terminal.
