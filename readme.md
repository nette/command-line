Nette Command-Line
==================

[![Downloads this Month](https://img.shields.io/packagist/dm/nette/command-line.svg)](https://packagist.org/packages/nette/command-line)
[![Coverage Status](https://coveralls.io/repos/github/nette/command-line/badge.svg?branch=master)](https://coveralls.io/github/nette/command-line?branch=master)
[![Latest Stable Version](https://poser.pugx.org/nette/command-line/v/stable)](https://github.com/nette/command-line/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/nette/command-line/blob/master/license.md)

Are you writing a PHP script that runs from the command line? Whether it's a little helper for one afternoon or a tool with a whole set of commands, this library does all the tedious work around it for you:

- Reads options and arguments and checks their values
- Builds the help and clear error messages
- Handles tools with several commands too
- Prints in color, but switches colors off by itself in a pipe or a log
- Has no dependencies


Installation
============

```shell
composer require nette/command-line
```

It requires PHP version 8.2 and supports PHP up to 8.6.

If you like Nette, **[please make a donation now](https://nette.org/donate)**. Thank you!


First Script
============

It's best shown with an example. Say you have a pile of images and want to convert them to another format. Let's write a script `convert.php` for it, to be called like this:

```shell
php convert.php photo.jpg logo.png --format png
php convert.php photo.jpg --dry-run
```

The command line is made of three kinds of items. **Arguments** are plain values without a dash, here the file names. An **option** has a name and a value: `--format png` says which format to convert to, and can be shortened to `-f png`. A **flag** is just a switch with no value: `--dry-run` (`-n` for short) makes the script only print what it would do, without touching a single file.

How much code does it take to make all of this work? Surprisingly little. You write the conversion itself as an ordinary method, and the attributes on its parameters tell how each value is given on the command line:

```php
use Nette\CommandLine\Attributes as Cli;
use Nette\CommandLine\Command;
use Nette\CommandLine\Runner;

final class ImageConverter
{
	#[Cli\Command('Converts images to another format')]
	public function convert(
		#[Cli\Argument('Images to convert', repeatable: true)]
		array $files,
		#[Cli\Option('Target format', alias: '-f', enum: ['jpg', 'png', 'webp'])]
		string $format = 'webp',
		#[Cli\Flag('Only show what would be done', alias: '-n')]
		bool $dryRun = false,
	): int
	{
		// converts each file to $format, with $dryRun only prints what it would do
		return 0;
	}
}
```

Notice that you never touch `$argv` or parse anything. The parameter `$format` becomes the option `--format`, `$dryRun` the flag `--dry-run`, and the default value of a parameter applies when the user leaves the option out. The texts in the attributes describe the items and show up in the help. `repeatable: true` says there may be more files, so the method gets them as an array. `enum` lists the allowed values, so the script refuses `--format gif` before the method is even called.

All that's left is to run the method. Below the class, in the same file `convert.php`, add:

```php
require __DIR__ . '/vendor/autoload.php';

$converter = new ImageConverter;
$program = Command::from($converter->convert(...));
$runner = new Runner($program, version: 'Converter 1.0');
exit($runner->run());
```

Writing `$converter->convert(...)` doesn't call the method, it passes it on. `Command::from()` reads from it what the script accepts, and `Runner` takes care of the rest: it parses the command line, checks the values and calls the method. Whatever the method returns becomes the exit code of the script. That's how the shell, CI or another script learns how things went: 0 means success, anything else a problem.

And since people at the command line have their habits, the script copes with all the usual spellings: `--format png`, `--format=png` and `-f png` mean the same, single-letter options can be stuck together (`-nf png` is `-n -f png`), and options may stand anywhere between the files.


Help and Errors
===============

Now try running `php convert.php --help`. `Runner` has given the script the options `--help` and `--version`, and built the help from the descriptions in the attributes:

```
$ php convert.php --help
Converter 1.0

Usage: convert.php [options] <files>...

Converts images to another format

Arguments:
  <files>...                   Images to convert

Options:
  -f, --format <jpg|png|webp>  Target format (default: webp)
  -n, --dry-run                Only show what would be done
  -h, --help                   Print this help
  -V, --version                Print the name and the version
```

You didn't have to write it, and yet it is complete and aligned, and in a terminal it comes in color. And since it comes from the same method that does the work, it will never claim anything other than what the script really can do.

The script is just as well prepared for mistakes. When the user makes a typo or enters nonsense, they don't get a stack trace but a clear answer:

```
$ php convert.php --fromat png photo.jpg
Error: Unknown option `--fromat`. Did you mean `--format`?

$ php convert.php --format gif photo.jpg
Error: Option `--format`: expects `jpg`, `png` or `webp`, `gif` given.
```

The help is printed right below the message, so the user doesn't have to look anything up, and the script ends with exit code 2, the usual way to report a wrong command line. Your method isn't called at all in that case, so it never runs with nonsensical values.


Without Attributes
==================

Attributes are the most comfortable way: each option is described right next to the parameter that receives it, the method gets the values straight in its parameters, and there's no separate definition to keep in sync with the code. Underneath them, though, lies a plain API you can use directly. It comes in handy for a quick script without a class, or when you put the options together at runtime:

```php
use Nette\CommandLine\Command;
use Nette\CommandLine\Parser;

$command = new Command('convert');
$command->addOption('--format', 'Target format', alias: '-f', enum: ['jpg', 'png', 'webp']);
$command->addArgument('files', 'Images to convert', repeatable: true);

$args = (new Parser)->parse($command);
$args->get('--format');  // 'png'
$args->get('files');     // ['photo.jpg', 'logo.png']
```

Keep in mind, though, that `Parser` only reads. It doesn't handle the help or report mistakes for you, so a wrong command line ends with an exception. How to add that, or how to bring in `Runner` here as well, is shown [below](#running-the-program), together with everything options and arguments can do.


Several Commands
================

Scripts have a habit of growing. Today it converts images, tomorrow it should shrink them too. Such tools are usually controlled by commands, much like `composer install` and `composer update`:

```shell
php images.php convert photo.jpg --format png
php images.php resize photo.jpg --width 800
php images.php --quiet convert photo.jpg
```

You don't have to rewrite anything. Each command is one method with the `#[Cli\Command]` attribute, just like `convert()` above, and a whole class of such methods is added in one call. Options that apply to all commands, like `--quiet` on the last line, are added to the program as a whole:

```php
$tools = new ImageTools;  // contains the methods convert() and resize()
$program = new Command('images');
$program->addFlag('--quiet', 'Print nothing but errors', alias: '-q');
$program->addCommandsFrom($tools);

$runner = new Runner($program);
exit($runner->run());
```

Each command gets its own help (`php images.php convert --help`), and commands can be nested, so `php images.php cache clear`, for example, is the command `clear` inside the command `cache`. More in [Commands](#commands).


Flags, Options and Arguments
============================

Let's recall how our script is called:

```shell
php convert.php photo.jpg logo.png --format png --dry-run
```

There are three kinds of items on the command line:

- **arguments** are values without a dash, here `photo.jpg` and `logo.png`; they are told apart by their order, which is why they are called positional,
- an **option** has a name and a value, here `--format png`,
- a **flag** has just a name and works as a switch, here `--dry-run`.

So far we have described the script with attributes. Now let's look at the API underneath them. Everything in this part applies to attributes as well, which take the same settings, only you write them on the parameters of a method. You describe the script with a [Nette\CommandLine\Command](https://api.nette.org/commandline/master/Nette/CommandLine/Command.html) object and its methods `addArgument()`, `addOption()` and `addFlag()`:

```php
use Nette\CommandLine\Command;
use Nette\CommandLine\Parser;

$command = new Command('convert', 'Converts images to another format');
$command->addArgument('files', 'Images to convert', repeatable: true);
$command->addOption('--format', 'Target format', alias: '-f');
$command->addFlag('--dry-run', 'Only show what would be done', alias: '-n');

$parser = new Parser;
$args = $parser->parse($command);
```

Each `add*()` method takes a name, a description that will show up in the help, and then named arguments with further settings, like `alias` for the short form here. [Nette\CommandLine\Parser](https://api.nette.org/commandline/master/Nette/CommandLine/Parser.html) reads the command line according to this description and returns a [Nette\CommandLine\ParseResult](https://api.nette.org/commandline/master/Nette/CommandLine/ParseResult.html) object. Its `get()` method reads a value by name, exactly as you wrote it, dashes included:

```php
$args->get('files');      // ['photo.jpg', 'logo.png']
$args->get('--format');   // 'png', or null when the option is missing
$args->get('--dry-run');  // true, or null when the flag is missing
```

If you make a typo in the name, such as `$args->get('--fromat')`, you get an exception. That way a typo can't pass for an option the user simply didn't give. `toArray()` returns all the values at once.

`parse()` reads the command line the script was started with. You can also pass it your own array of individual words, without the script name, which comes in handy in tests:

```php
$args = $parser->parse($command, ['photo.jpg', '--format', 'png']);
```

Two methods help when you need more than the value itself. `isEmpty()` tells you the user wrote nothing at all after the script name. `wasProvided('--format')` tells you whether the user really gave the option, so you can tell that they wrote `--format webp` even when `webp` is also the default value. That's useful, for example, when merging the command line with a configuration file.

Watch out for one thing: `Parser` only reads. When the user gets something wrong, it throws an exception, and it doesn't print the help by itself either. Both are handled for you by the [Runner](#running-the-program). If you want everything in your own hands, see the sections [Help](#help) and [Errors](#errors).


Flags
-----

A flag is a switch: when it's on the command line, its value is `true`, when it's missing, `null`:

```php
$command->addFlag('--dry-run', alias: '-n');
// --dry-run   → true
// -n          → true
// (missing)   → null
```

Some things you want switched on until the user turns them off. Keeping EXIF metadata during conversion, for instance. With `negatable: true` the flag also gets the opposite form `--no-…`, and the default value says what applies when the user writes neither:

```php
$command->addFlag('--metadata', 'Keep EXIF metadata', negatable: true, default: true);
// --metadata     → true
// --no-metadata  → false
// (missing)      → true
```

Single-letter flags can be stuck together into one word: `-nv` means the same as `-n -v`. The last letter may even belong to an option with a value, so `-nf png` is `-n -f png`.


Options
-------

An option carries a value. The user can separate it with a space or an equals sign, and use the alias instead of the long name:

```php
$command->addOption('--format', alias: '-f');
// --format png   → 'png'
// --format=png   → 'png'
// -f png         → 'png'
// --format       → error, the value is missing
// (missing)      → null
```

So the option itself is optional, but once the user uses it, they must write a value too. When the user gives the same option several times, the last value wins. If you want them all, use [repeated values](#repeated-values).

Sometimes an option comes in handy that may have a value but doesn't have to. Say `--backup` keeps the original files, and if the user adds a directory, stores them there. That's what `valueOptional: true` does:

```php
$command->addOption('--backup', valueOptional: true);
// --backup=old  → 'old'
// --backup      → true
// (missing)     → null
```

An optional value has to be attached with an equals sign. Writing `--backup old` would be ambiguous: is `old` the value of the option, or another file? So the library takes it as a file and `--backup` as an option without a value.

The exception is an option that also has a [list of allowed values](#allowed-values). There the value is taken even after a space, but only when it's one of the allowed ones, so nothing else gets swallowed by mistake. For `--strip` with the values `exif` and `all`, `--strip all` therefore means the value `all`, while `--strip photo.jpg` means the option without a value plus a file. That's why the definition refuses a command named like one of the values, and an argument equal to a value has to be written after `--`.


Arguments
---------

Arguments are values without a dash, told apart by their order. They are required unless you say otherwise:

```php
$command->addArgument('input');
// photo.jpg  → 'photo.jpg'
// (missing)  → error, the required argument is missing

$command->addArgument('output', optional: true);
// (missing)  → null
```

Arguments may stand anywhere, before the options or after them. For values that start with a dash, a few rules apply that you know from other tools:

```php
// convert.php -- -weird.jpg   → file '-weird.jpg', everything after -- is an argument
// convert.php -               → file '-', by convention the standard input
// convert.php --rotate -90    → --rotate gets the value '-90'
```

A negative number is thus an ordinary value. The only exception is a command that itself has an option named like a number, such as `-1` in `ls -1`. There every `-90` is taken for an option, and the user has to write a negative value with an equals sign, `--rotate=-90`, or as an argument after `--`.


Default Values
--------------

`default` sets the value of an option or an argument for the case the user doesn't give it:

```php
$command->addOption('--format', default: 'webp');
// --format png  → 'png'
// (missing)     → 'webp'
```

Don't confuse it with `valueOptional`. That one says what applies when the user writes the option without a value, whereas `default` applies when they don't write it at all. An option can have both:

```php
$command->addOption('--backup', valueOptional: true, default: 'backup');
// --backup=old  → 'old'
// --backup      → true
// (missing)     → 'backup'
```

The default value is returned exactly as you gave it, so it is neither checked nor converted. A required argument with a default value would make no sense, so the definition refuses it. Mark such an argument as optional.


Allowed Values
--------------

`enum` limits the value to a list. The script refuses anything else with a message that names the allowed values:

```php
$command->addOption('--format', enum: ['jpg', 'png', 'webp']);
// --format gif  → Option `--format`: expects `jpg`, `png` or `webp`, `gif` given.
```

The values are non-empty strings, so write numbers as strings too, such as `['1', '2']`. Instead of a list you can pass a backed enum and get its case straight away:

```php
enum Format: string
{
	case Jpg = 'jpg';
	case Png = 'png';
	case Webp = 'webp';
}

$command->addOption('--format', enum: Format::class, default: Format::Webp);
// --format png  → Format::Png
// (missing)     → Format::Webp
```


Repeated Values
---------------

With `repeatable: true` the user can give an option or an argument several times, and you get all the values in an array:

```php
$command->addOption('--exclude', 'Skip files matching the mask', repeatable: true);
// --exclude '*.tmp' --exclude 'thumb*'  → ['*.tmp', 'thumb*']
// (missing)                             → []

$command->addArgument('files', repeatable: true);
// photo.jpg logo.png  → ['photo.jpg', 'logo.png']
```

A repeated argument takes all the remaining values, so it has to be the last one.


Converting Values
-----------------

What comes from the command line is always a string. When you need a number or a path, give the option or the argument a `normalizer`, a function that checks the value and converts it. Ready-made ones live in the [Nette\CommandLine\Normalizers](https://api.nette.org/commandline/master/Nette/CommandLine/Normalizers.html) class: `int()` and `float()` convert a number, optionally within a range, and `realPath()` turns the path of an existing file into an absolute one:

```php
use Nette\CommandLine\Normalizers;

$command->addOption('--quality', normalizer: fn($v) => Normalizers::int($v, min: 1, max: 100));
// --quality 80   → 80
// --quality abc  → Option `--quality`: expects an integer, `abc` given.
// --quality 150  → Option `--quality`: expects a number from 1 to 100, `150` given.

$command->addOption('--watermark', normalizer: Normalizers::realPath(...));
// --watermark logo.png     → '/home/me/images/logo.png'
// --watermark missing.png  → Option `--watermark`: file path `missing.png` not found.
```

Without a range, passing the method itself is enough, `Normalizers::int(...)`. Your own normalizer is any function that takes a string and returns the converted value. When the value is wrong, it throws an exception, and the library turns it into a clear message naming the option, as in the example above. You can also call a ready-made normalizer from your own, for example when you want to adjust the path first:

```php
$command->addOption('--watermark', normalizer: fn($v) => Normalizers::realPath("watermarks/$v"));
```

A normalizer runs only on values that really came from the command line. It is called neither on the default value nor on the `true` of an option without its optional value.

A range no number fits into, such as `min` greater than `max`, is a mistake in the code, not in what the user typed. That's why it throws `ValueError`, which is not turned into a message for the user. From PHP 8.6 on, instead of the function `fn($v) => Normalizers::int($v, min: 1)` you can write the shorter `Normalizers::int(?, min: 1)`.


Thorough Validation
-------------------

For more complex rules you can pass the result on to [Nette Schema](https://doc.nette.org/en/schema). Command-Line doesn't depend on it, it's simply a second step. The parser returns strings as the user wrote them, and `null` for what they didn't give. So leave out the `null` values to let the defaults of the schema apply:

```php
use Nette\Schema\Expect;
use Nette\Schema\Processor;

$schema = Expect::structure([
	'--quality' => Expect::string()->pattern('[1-9][0-9]?|100')->castTo('int')->default(85),
	'files' => Expect::listOf('string'),
])->otherItems();

$values = array_filter($args->toArray(), fn($value) => $value !== null);
$options = (new Processor)->process($schema, $values);
```


Parameters in Code
------------------

The `add*()` methods return the parameter object, whose settings you can read back as properties, such as `$option->alias`. A parameter can't be changed later, so it is checked the moment it's added, and a mistake in the definition, such as an option name without a dash or a name used twice, throws `InvalidArgumentException` right away. Later you can reach a parameter with `getParameter()`. The second argument checks that it is an option, and static analysis then knows which properties it has:

```php
use Nette\CommandLine\Parameters\Option;

$format = $command->getParameter('--format', Option::class);
```

Not every setting makes sense for every kind. A flag has no value, so `addFlag()` has neither `enum` nor `normalizer`. Those belong to `addOption()` and `addArgument()`, while `valueOptional` and `valueName` belong to `addOption()` alone.


Commands
========

When a script does several things, it is usually controlled by commands. Our image tool will convert and also shrink images, and it will have one option shared by everything:

```shell
php images.php convert photo.jpg --format png
php images.php resize photo.jpg --width 800
php images.php --quiet convert photo.jpg
```

You add a command with `addCommand()`. It returns a new `Command`, which you set up exactly like the program itself:

```php
$program = new Command('images');
$program->addFlag('--quiet', 'Print nothing but errors', alias: '-q');

$convert = $program->addCommand('convert', 'Converts images to another format');
$convert->addArgument('files', 'Images to convert', repeatable: true);
$convert->addOption('--format', 'Target format', alias: '-f', enum: ['jpg', 'png', 'webp']);

$resize = $program->addCommand('resize', 'Shrinks images to the given width');
$resize->addArgument('files', 'Images to resize', repeatable: true);
$resize->addOption('--width', 'Width in pixels', normalizer: Normalizers::int(...));
```

The command is recognized by the first word without a dash, and after parsing, `$args->command` tells you which one it was. When the user writes no command, `$args->command` is the program itself. If a command should be required, create the program with `commandRequired: true`.

Options of the program, like `--quiet` here, apply in all commands, and the user can write them before the command or after it: `images.php -q convert photo.jpg` and `images.php convert photo.jpg -q` mean the same. An option of another command is refused, and the library tells where it belongs:

```
$ php images.php convert photo.jpg --width 800
Error: Option `--width` belongs to command `resize`.
```

The result holds only the values of the program and of the selected command. Neighboring commands may use the same option names, so `convert` could well have its own `--width` with a different meaning.


Nested Commands
---------------

A command can have its own commands, and those their own again. Managing the thumbnail cache, for instance:

```php
$cache = $program->addCommand('cache', 'Manages the thumbnail cache');
$cache->addCommand('clear', 'Deletes all cached thumbnails');
$cache->addCommand('stats', 'Shows how much space the cache takes');
```

The user then writes `php images.php cache clear`. A command that has its own commands can't take arguments, because there would be no telling whether the word after it is an argument or a command.


Handlers
--------

What's left is to say what should happen for which command. The most comfortable way is to give each command a handler, a method or function that does its work. You pass it as `handler:` right when creating the command:

```php
final class ImageTools
{
	public function convert(array $files, ?string $format, ?bool $quiet): int
	{
		// converts the files, prints nothing with $quiet
		return 0;
	}
}

$tools = new ImageTools;
$convert = $program->addCommand('convert', 'Converts images to another format', handler: $tools->convert(...));
```

You don't pass the values to the handler yourself. Each of its parameters gets a value by its name: `$files` the argument `files`, `$format` the option `--format`, and since the options of the program count too, `$quiet` gets `--quiet`. A name of several words is converted: `$maxWidth` becomes `--max-width`. A parameter typed `ParseResult` gets the whole result, for example when you need `wasProvided()`, and a parameter typed `Command` gets the selected command.

The selected command is then run by `run()`. It returns whatever the handler returned, and if the handler returns nothing, that means 0:

```php
$args = $parser->parse($program);
exit($args->run());
```

Even more comfortable is handing the program to the [Runner](#running-the-program), which adds the help and error reporting on top.

The types of the parameters are checked against what the option can give. An option the user didn't give and that has no default value is `null`, so it belongs in `?string`, not in `string`. If the handler had `string $format`, the library reports it with an exception the very first time the script runs, before anything is done, and even for commands that aren't being run at the moment:

```
Command 'convert': parameter $format of handler ImageTools::convert() does not accept null, which option --format gives when absent.
```

Other mistakes in the signature are reported the same way: a parameter with no option or argument to bind to, a parameter with a default value the input would always override anyway, a variadic parameter, and a parameter passed by reference. A normalizer has to declare its return type, otherwise there would be nothing to compare the parameter with. A repeated option or argument arrives as an array.


Commands Described by Attributes
--------------------------------

A handler can also describe its command itself, right next to its code. That's how we did it at the beginning. `#[Command]` gives the method a description, and `#[Flag]`, `#[Option]` and `#[Argument]` turn its parameters into flags, options and arguments:

```php
use Nette\CommandLine\Attributes as Cli;

final class ImageTools
{
	#[Cli\Command('Converts images to another format')]
	public function convert(
		?bool $quiet,
		#[Cli\Argument('Images to convert', repeatable: true)]
		array $files,
		#[Cli\Option('Target format', alias: '-f', enum: ['jpg', 'png', 'webp'])]
		string $format = 'webp',
	): int
	{
		// converts the files, prints nothing with $quiet
		return 0;
	}

	#[Cli\Command('Shrinks images to the given width')]
	public function resize(/* ... */): int
	{
		// ...
	}
}

$tools = new ImageTools;
$program->addCommandsFrom($tools);
```

`addCommandsFrom()` adds all the public methods with the `#[Cli\Command]` attribute, in the order they appear in the class. A single method is added with `addCommandFrom($tools->convert(...))`. The result is perfectly ordinary commands, as if you had called `addCommand()` and `addOption()` yourself.

The names are derived: the command from the name of the method, so `removeDuplicates()` becomes `remove-duplicates`, and the options from the names of the parameters, so `$maxWidth` becomes `--max-width`. You set a different name in the attribute with `name:`. The default value of a parameter becomes the default value of the option, an argument with a default value is optional, and a parameter typed by a backed enum accepts its cases as the allowed values.

A parameter without an attribute, like `$quiet` above, binds to an option by its name, here to `--quiet` of the program. It must not have a default value, since the option of the program carries that, and so it has to come before the parameters that have one.

The attributes `#[Flag]`, `#[Option]` and `#[Argument]` take the same settings as `addFlag()`, `addOption()` and `addArgument()`, only the name and the default value come from the parameter. `#[Command]` takes `description`, `usage`, `commandRequired` and `name`.

The attribute `#[Command]` has the same name as the class `Nette\CommandLine\Command`. That's why we import the attributes under the alias `Cli` and write `#[Cli\Command]`.

A program without commands is described the same way: `Command::from()` creates the whole program from a handler, just as we did at the beginning. Its name isn't derived from the method, which names an action rather than the program, so without `name:` the help names it after the script file. Such a program is an ordinary `Command`, so you can add commands to it as well.

An attribute can only hold a constant expression, which is why a normalizer is written as an array of a class and a method. A ready-made one as `[Normalizers::class, 'int']`, your own as `[self::class, 'parseWidth']`. From PHP 8.5 on you can write a `static function` right into the attribute. When a normalizer also needs a range, write a static method that passes it on:

```php
final class ImageTools
{
	#[Cli\Command('Shrinks images to the given width')]
	public function resize(
		#[Cli\Argument('Images to resize', repeatable: true)]
		array $files,
		#[Cli\Option('Width in pixels', normalizer: [self::class, 'parseWidth'])]
		int $width = 800,
	): int
	{
		// shrinks the files to $width
		return 0;
	}

	public static function parseWidth(string $value): int
	{
		return Normalizers::int($value, min: 1);
	}
}
```

With attributes, the names of your methods and variables become the interface of your tool. When you rename `$maxWidth` to `$widthLimit`, `--max-width` turns into `--width-limit`, and every script that calls your tool stops working. A [test of the help](#testing) catches such a change.


Options Shared by Several Commands
----------------------------------

Commands often need the same options. Both `convert` and `resize` store their results in a directory, for instance, and both can do a dry run. So that you don't have to repeat them, describe them once in the constructor of a class, and let each handler take it as a parameter:

```php
final readonly class Output
{
	public function __construct(
		#[Cli\Option('Directory for the results', valueName: 'dir')]
		public ?string $outputDir = null,
		#[Cli\Flag('Only show what would be done', alias: '-n')]
		public bool $dryRun = false,
	) {
	}
}

final class ImageTools
{
	#[Cli\Command('Converts images to another format')]
	public function convert(
		#[Cli\Argument('Images to convert', repeatable: true)]
		array $files,
		Output $output,
	): int
	{
		// stores the results in $output->outputDir
		return 0;
	}
}
```

`convert` now accepts `--output-dir` and `--dry-run`, and so does every other command whose handler takes `Output`. When the command runs, the library creates the object from the values of these options and passes it to the handler. In the help, the options of the object show up together, at the place of the parameter.

The constructor follows the same rules as a handler. A handler can also take several such objects, say one with what all commands share and another with what only some of them do. This works only for commands described by attributes.


Dispatching Without Handlers
----------------------------

Handlers aren't mandatory. In a small script you can find out the selected command and branch yourself, best with `match`:

```php
$args = $parser->parse($program);

exit(match ($args->command) {
	$convert => convertImages($args->get('files'), $args->get('--format')),
	$resize => resizeImages($args->get('files'), $args->get('--width')),
	$program => showUsage(),
});
```

Since `match` has no `default` arm, it throws `UnhandledMatchError` if you forget a command. The help and errors, however, are up to you in this case, see [Help](#help) and [Errors](#errors).


Running the Program
===================

Parsing the line, answering `--help` and `--version`, reporting a mistake and running the handler is done the same way in every tool. That's why [Nette\CommandLine\Runner](https://api.nette.org/commandline/master/Nette/CommandLine/Runner.html) does it all at once and returns the exit code:

```php
use Nette\CommandLine\Runner;

$runner = new Runner($program, version: 'Images 1.0');
exit($runner->run());
```

`Runner` adds the option `--help` (`-h` for short) to the program, which prints the help of the selected command, so `images.php convert --help` shows the help of `convert`. When you give a version, it also adds `--version` (`-V` for short) and puts the version at the top of the help as well.

When the user gets the line wrong, `Runner` prints the message with the help of the command they got wrong below it, to the error output. Then it returns exit code 2. That's the convention for a wrong command line, and it sets it apart from code 1, with which a tool reports that it found something or that something failed. It behaves the same way when a handler throws `UsageException`, see [Errors](#errors). Other exceptions from the handler pass through, as if `Runner` weren't there.

You can adjust the behavior with named parameters:

```php
$runner = new Runner(
	$program,
	version: 'Images 1.0',
	helpWhenEmpty: true,  // running without a single word prints the help
	colorFlag: true,      // adds the option --no-color, which turns colors off
	usageExitCode: 3,     // a code other than 2 for a wrong command line
	errorExitCode: 1,     // prints exceptions of the handler as "Error: ..." and returns 1
	out: $console,        // the console for the output, so that --no-color applies to it too
	err: $errors,         // the console for the error output, by default it writes to STDERR
);
```

`errorExitCode` doesn't apply to `Error`, a mistake in the code, which always passes through. The options `Runner` adds show up in the help among the options of the program. When the program already has an option of the same name, `Runner` leaves it alone and just handles it. When the program already uses a short form, such as `-h` for `--host`, `Runner` doesn't use it for `--help`. `--no-color` is read before the line is parsed, so even an error message is printed without colors.

You can color the version. Instead of a string, pass a function that gets the console: `version: fn(Console $console) => $console->color('aqua', 'Images') . ' 1.0'`. That way `--no-color` applies to it too.

`Runner` works with all handlers, whether described by attributes or passed as `handler:`. A program without commands but with a handler can be created without attributes too, `new Command('convert', handler: ...)`.


Help
====

The help is built by [Nette\CommandLine\HelpRenderer](https://api.nette.org/commandline/master/Nette/CommandLine/HelpRenderer.html) from the descriptions you gave. That's why it always matches what the script really accepts. It draws it for the program or for any command, aligns the descriptions and wraps them to the width of the terminal, and in a terminal it colors them:

```php
use Nette\CommandLine\HelpRenderer;

$renderer = new HelpRenderer;
$renderer->render($command);
```

For the `convert` script with the options from the first part it looks like this:

```
Usage: convert [options] <files>...

Converts images to another format

Arguments:
  <files>...                   Images to convert

Options:
  -f, --format <jpg|png|webp>  Target format (default: webp)
  -q, --quality <value>        Quality from 1 to 100
  --backup[=dir]               Keep the originals, optionally in the given
                               directory
  --exclude <mask>...          Skip files matching the mask
  --[no-]metadata              Keep EXIF metadata
  -n, --dry-run                Only show what would be done
```

Notice how the help shows the different kinds: an optional value as `--backup[=dir]`, repeated values with three dots, a flag with an opposite form as `--[no-]metadata`, and the default value after the description.

At the top is the usage line. It is generated by itself, or you replace it with your own via `usage:` in `new Command()` or `addCommand()`. The description of the command follows below it. The help of a command shows its arguments and options, and under the heading `Global options:` the options of the program that apply in the command as well.

A few settings shape only the help:

- `hidden: true` hides a parameter from the help, while it still works,
- `valueName: 'dir'` names the value, as in `--backup[=dir]`; for an option with `enum` it replaces the list of values, which comes in handy when the list is long,
- `defaultDescription: 'current directory'` describes the default value in words; an empty string leaves it out, so a password or a token given as the default never shows up in the help.

Without `defaultDescription`, the help shows the default value as `(default: webp)`, as long as there is something to show. It leaves out a boolean, an array and an empty string.

Headings and texts of your own are added with `addSection()` and `addText()`, in the order you call them. A text is printed exactly as you wrote it, with no wrapping and no colors. When you add no heading, the help puts `Options:`, `Arguments:` and `Commands:` in by itself. Write code in the descriptions in backticks, as in Markdown. In a terminal it is drawn in a color of its own, in plain text the backticks stay.

Instead of printing the help, you can get it as a string with `renderToString()`. Without a console it is plain text with no colors, which suits a file or a test. You can set the width yourself:

```php
$renderer = new HelpRenderer(width: 100);
$text = $renderer->renderToString($command);
```


Your Own `--help` and `--version`
-----------------------------

`Runner` adds both options and handles them for you. Without it you do it yourself, and one thing deserves attention: when the script has a required argument, `convert.php --help` would end with an error that the argument is missing. So mark the flag as standalone, and it answers on its own:

```php
$command->addFlag('--help', 'Print this help', alias: '-h', standalone: true);
$command->addFlag('--version', standalone: true);

$args = $parser->parse($command);
if ($args->get('--help')) {
	$renderer = new HelpRenderer;
	$renderer->render($args->command);
	exit(0);
}
```

When the user uses a standalone flag, the parser checks and requires nothing. It returns that flag as `true`, the other flags as they were given, and everything else with its default value. So you print the help before the script starts reading its configuration or checking files. That's why a setting that should work together with `--help` is best made a flag. For example, `--help --no-color` prints the help without colors.

With commands, the command is selected first, so `images.php convert --help` gives you exactly `convert` in `$args->command`. The line still has to make sense, though: the parser refuses an unknown option as always.


Errors
======

When the user gets the line wrong, the parser throws [Nette\CommandLine\ParseException](https://api.nette.org/commandline/master/Nette/CommandLine/ParseException.html). `Runner` catches it and prints it. Without it, you do it like this:

```php
use Nette\CommandLine\UsageException;

try {
	$args = $parser->parse($program);
	exit($args->run());
} catch (UsageException $e) {
	$renderer = new HelpRenderer;
	$renderer->renderError($e);
	exit(2);
}
```

`renderError()` prints the message with the help of the command the user got wrong below it, to the error output. So the user sees right away both what's wrong and how to write it correctly:

```
Error: Unknown option `--fromat`. Did you mean `--format`?

Usage: images convert [options] <files>...
...
```

`ParseException` is a kind of [Nette\CommandLine\UsageException](https://api.nette.org/commandline/master/Nette/CommandLine/UsageException.html). You can throw that one from a handler yourself when you run into a mistake the description of the options can't express, such as two options that can't be used together. Give it the command whose help should be printed, which the handler gets when it has a parameter typed `Command`:

```php
public function convert(Command $command, bool $stdin, array $files): int
{
	if ($stdin && $files) {
		throw new UsageException('Files cannot be combined with `--stdin`.', $command);
	}
	// ...
}
```

Write code in the messages in backticks, just like in descriptions. In a terminal `renderError()` draws it in color and without the backticks, in a log the backticks stay. If you want to print a message your own way, the `formatText()` method formats it the same way.

Common messages, as a terminal with colors shows them:

| Message | Cause |
|---|---|
| Option `--format` requires a value. | option without its required value |
| Option `--dry-run` does not accept a value. | a value given to a flag |
| Unknown option `--fromat`. Did you mean `--format`? | unknown option |
| Missing required argument `<files>`. | a required argument is missing |
| Unexpected arguments `b`, `c`. | extra arguments |
| Option `--format`: expects `jpg`, `png` or `webp`, `gif` given. | value not among the allowed ones |
| Unknown command `conver`. Did you mean `convert`? | unknown command |
| Missing command. | a command is required but missing |
| Option `--width` belongs to command `resize`. | option of another command |

The messages are in English. If you want to word them differently or translate them, `ParseException` has everything you need: `reason` says what was wrong, as a case of the `ParseFailure` enum (such as `ParseFailure::UnknownOption`), `parameter` is the parameter the error concerns, and `token` is the word of the command line it's about, exactly as the user wrote it, or `null` when something is missing. Since `token` comes from the user, strip control characters from it before printing it.

Mistakes in your definition, such as an option name without a dash, a name used twice or a required argument after an optional one, are not mistakes of the user. They throw `InvalidArgumentException`, and `Runner` doesn't catch them at all.


Testing
=======

You test the script without running it from the command line. You pass `run()` the individual words of the command line without the script name, and catch the error output in memory. A console that doesn't write to a terminal uses no colors, so you compare plain text. The examples use [Nette Tester](https://tester.nette.org/en), but any testing framework will do:

```php
$output = fopen('php://memory', 'w+');
$runner = new Runner($program, err: new Console($output));
$exitCode = $runner->run(['convert', '--format', 'gif', 'photo.jpg']);

Assert::same(2, $exitCode);
Assert::contains('expects `jpg`, `png` or `webp`', stream_get_contents($output, offset: 0));
```

If you only want to check how a line is understood, without running anything, pass the words to the parser:

```php
$args = $parser->parse($program, ['convert', 'photo.jpg', '-f', 'png']);
Assert::same('png', $args->get('--format'));
```

A single command is tested by passing it to the parser together with the whole line. The parser checks that the line really leads to that command:

```php
$args = $parser->parse($convert, ['convert', 'photo.jpg']);
```

The help deserves a test of its own. It is in fact a description of your tool's interface, so a test comparing it with a stored text catches every unwanted change, such as a renamed option. A fixed width makes sure the result doesn't depend on the terminal the test runs in:

```php
$renderer = new HelpRenderer(width: 80);
Assert::matchFile(__DIR__ . '/help.txt', $renderer->renderToString($program));
```

You create the file `help.txt` the first time by saving into it what `renderToString()` returns, and checking that it is right.


Terminal Output
===============

A script doesn't only read, it also writes. That's what the `Console` class is for, and it follows where the output goes. In a terminal it writes in color, and when you redirect the output to a file or the script runs in CI, it leaves the colors out by itself, so the log stays clean. The same with progress: in a terminal it shows on a line that keeps being rewritten, and it never gets into the log at all.

You create the console once and hand it to the class that needs it, for example in the constructor:

```php
final class ImageConverter
{
	public function __construct(
		private Console $console,
	) {
	}

	#[Cli\Command('Converts images to another format')]
	public function convert(
		#[Cli\Argument('Images to convert', repeatable: true)]
		array $files,
	): int
	{
		foreach ($files as $file) {
			$this->console->setStatus("Converting $file");  // the progress line
			// ...
			$this->console->writeLine("$file converted", 'green');
		}
		return 0;
	}
}

$console = new Console;
$converter = new ImageConverter($console);
```


Colorful Output
---------------

A [Nette\CommandLine\Console](https://api.nette.org/commandline/master/Nette/CommandLine/Console.html) object writes to one output, the screen by default, and can color the text:

```php
use Nette\CommandLine\Console;

$console = new Console;
$console->writeLine('3 images converted', 'green');
$console->writeLine('logo.png skipped, it is already WebP', 'yellow');
$console->writeLine($console->color('gray', 'Results in ') . $outputDir);  // a line of several colors
```

Colors are added only by methods, never by markup in the text. Some libraries look for markup like `<info>` in the text, so XML, HTML or user input has to be escaped first or it breaks. Here everything is printed exactly as it is.

Whether the text comes out in color doesn't depend on your code but on where the output goes. In a terminal it's in color. When you redirect the output to a file, pipe it to another program or the script runs in CI, the same code prints plain text, because `color()` and `link()` then add nothing. Just watch out for text from elsewhere that carries its own colors, such as the output of another program. Remove them with `Ansi::strip()` if needed.


### Two Outputs

Every program has two outputs: the standard one (`STDOUT`) for results, and the error one (`STDERR`) for messages and errors. The user can redirect each of them somewhere else, for example the results into a file while the errors stay on the screen. That's why each output gets its own console, which finds out by itself whether its output leads to a terminal:

```php
$out = new Console(STDOUT);
$err = new Console(STDERR);
```

Without a parameter, the console writes to `STDOUT`. When the program reading your output stops early, like `head` in `php images.php list | head -1`, the rest of the output is quietly dropped instead of raising an error on every write.


### Colors

You give a color as `'foreground'` or `'foreground/background'`, such as `'white/blue'`. The basic names `black`, `gray`, `silver`, `white`, `navy`, `blue`, `green`, `lime`, `teal`, `aqua`, `maroon`, `red`, `purple`, `fuchsia`, `olive` and `yellow` are drawn by the terminal according to its color theme. A color that should look the same everywhere is written in hex, `'#87D7FF'` or the short `'#8DF'`, or as a number 0–255 of the extended palette, such as `'117'`.

An unknown color throws an exception even when colors are off, so you catch a typo right away. The color `null` means no color, which comes in handy in conditions: `$console->color($ok ? 'green' : null, $text)`.

Not every terminal can show every color. The console finds out by itself and draws a color as faithfully as possible: exactly in a modern terminal, as the nearest color it can in an older one. How many colors the terminal handles is expressed by [Nette\CommandLine\ColorDepth](https://api.nette.org/commandline/master/Nette/CommandLine/ColorDepth.html): `TrueColor` (any color), `Ansi256` (a palette of 256 colors), `Ansi16` (16 basic colors) and `None` (no colors).

You can influence the behavior with environment variables that other tools know too. [NO_COLOR](https://no-color.org) turns colors off. [FORCE_COLOR](https://force-color.org) turns them on even when the output goes to a file or a pipe, and its value `2` or `3` asks for at least `Ansi256` or `TrueColor`. `COLORTERM` set to `truecolor` or `24bit` says the terminal can show any color.


### The `--no-color` Option

Users often want to turn colors off right on the command line. When you run the program with the [Runner](#running-the-program), it's enough to tell it `colorFlag: true` and pass the consoles concerned. Runner adds the option `--no-color` and takes care of the rest:

```php
$out = new Console(STDOUT);
$err = new Console(STDERR);
$tools = new ImageTools($out);

$program = new Command('images');
$program->addCommandsFrom($tools);
$runner = new Runner($program, colorFlag: true, out: $out, err: $err);
exit($runner->run());
```

Without Runner you turn colors off yourself with the `setColorDepth()` method, or right in the constructor:

```php
use Nette\CommandLine\ColorDepth;

$console = new Console(STDOUT, colorDepth: ColorDepth::None);
```

You can also ask the console how things stand:

```php
$console->hasColors();      // whether it prints in color
$console->getColorDepth();  // how many colors it uses, such as ColorDepth::Ansi256
$console->isTerminal();     // whether the output leads to a terminal where someone is watching
$console->getWidth();       // width of the terminal in columns, 80 outside a terminal
```

`hasColors()` and `isTerminal()` answer two different questions. Use the first to decide whether to color, the second to decide whether to show things that only make sense to a person at the screen, such as a progress line or a question. A user can turn colors off and still sit at a terminal.

`getWidth()` asks the terminal only once and remembers the result, so only a new console notices that the window was resized. The `COLUMNS` environment variable takes precedence, which comes in handy when you want to see how the output looks in a narrow terminal.


Clickable Links
---------------

A link is composed just like a color. In a terminal, `link()` turns the text into a link you can click. In a file, a pipe or without colors it prints the text followed by the address in parentheses, so the address doesn't disappear from the log:

```php
$console->writeLine('See ' . $console->link('https://nette.org'));
$console->writeLine($console->link('https://nette.org', 'the manual'));  // outside a terminal 'the manual (https://nette.org)'
```

`link()` refuses an address with control characters, because they could end the link early and send the terminal commands of their own. Accented letters and other characters beyond ASCII are fine.


Progress and Status Lines
-------------------------

During longer work it helps to show what the script is doing and how much is done. That's what `setStatus()` is for. It draws a line at the end of the output, the next printout erases it and is printed above it, and the next `setStatus()` draws the line again. The user thus sees the results piling up and below them one line that keeps changing:

```
photo.jpg converted
logo.png converted
3/10  holiday.jpg
```

In code it looks like this:

```php
foreach ($files as $i => $file) {
	$console->setStatus(sprintf('%d/%d  %s', $i + 1, count($files), $file));
	// ... converts the file
	$console->writeLine("$file converted");  // erases the status line and prints above it
}

$console->clearStatus();
```

The status line is only drawn in the terminal, it isn't written to the output. When the output doesn't lead to a terminal, `setStatus()` does nothing at all, so a log file holds only the real lines. There can be several status lines at once, each newline character starts another one. A line that is too long is cut to the width of the terminal, because a wrapped one couldn't be redrawn.

While the status line is visible, the cursor is hidden. It shows up again when you clear the status, when the console is released, and also at the end of the script, even after `exit()`, an uncaught exception or a fatal error. Only Ctrl+C ends PHP immediately, so your application has to handle that one itself.

A status line is erased only by the console that drew it. If you have one console for results and another for errors, draw the status on the one the errors go to. Otherwise a warning printed during the work would land on top of the status line.


Measuring Text
--------------

When you want to print aligned columns, `strlen()` won't help you. Colors are stored in the text as invisible escape sequences that take no space, and a Chinese character or an emoji takes two columns on the screen. The [Nette\CommandLine\Ansi](https://api.nette.org/commandline/master/Nette/CommandLine/Ansi.html) class measures and cuts text the way the terminal shows it:

```php
use Nette\CommandLine\Ansi;

Ansi::measure($console->color('red', 'error'));  // 5, the escape sequences don't count
Ansi::pad($console->color('red', 'error'), 10);  // pads to 10 columns, not to 10 bytes
Ansi::truncate($path, 30, keepEnd: true);        // cuts the front so the file name stays
Ansi::strip($text);                              // the text without escape sequences
```

Widths follow the Unicode tables, so emoji and flags are measured correctly too. Control characters, such as a tab, take no column. `strip()` removes only escape sequences and leaves other control characters in the text, so it won't make untrusted text safe to print.


Asking the User
---------------

Sometimes a script needs a decision only a person can make. Overwrite an existing file? Skip an image that can't be read? [Nette\CommandLine\Prompt](https://api.nette.org/commandline/master/Nette/CommandLine/Prompt.html) asks the question and waits until the user answers with one of the choices you allow:

```php
use Nette\CommandLine\Prompt;

$prompt = new Prompt($console);
if ($prompt->isInteractive()) {
	$answer = $prompt->choose('Overwrite photo.webp?', ['y', 'n', 'a', 'q'], default: 'n');
}
```

The user sees `Overwrite photo.webp? [y/N/a/q]`, and the default choice is the one in capitals. Which choices you offer is up to you, here they could be yes, no, yes to all and quit. Until the user answers with one of them, the prompt asks again. Letter case doesn't matter, and `choose()` returns the choice exactly as you declared it. Enter on an empty line picks the default, and when there is none, the prompt asks again.

When each choice deserves a few words, pass them as an array with descriptions, and the prompt prints a menu above the question:

```php
$answer = $prompt->choose('What now?', [
	's' => 'Skip this image',
	'r' => 'Retry',
	'c' => 'Cancel the conversion',
], default: 'r');
```

```
  [s] Skip this image
  [r] Retry
  [c] Cancel the conversion
What now? [s/R/c]
```

When the input ends, for example because the user pressed Ctrl+D, `choose()` returns `null`, never the default. A closed input thus can't quietly answer yes. What that means for the script is your decision, such as `?? 'q'`.

The prompt asks only when both the input and the console lead to a terminal, so it never takes an answer from a pipe nobody is sitting at. Otherwise `choose()` throws `LogicException` before it prints or reads anything. So first ask `isInteractive()` and decide what the script does without a person, for example stop with a hint to use a command-line option such as `--overwrite`. Give the prompt the console that draws the status line: the question then erases it, and the next status is drawn on a clean line after the answer.


Upgrading from 1.x
==================

Version 2.0 is a new API with no compatibility layer: in 1.x a single `Parser` held the definition, parsed the line and printed the help text it had been given, while in 2.0 a `Command` is the definition, `Parser` reads a line against it and `HelpRenderer` draws the help from it.

The [Nette plugin for Claude Code and Codex](https://doc.nette.org/ai/command-line/agent-plugins) includes a guide by which your coding agent rewrites 1.x code to 2.0 faithfully and reports every place where 2.0 behaves differently.
