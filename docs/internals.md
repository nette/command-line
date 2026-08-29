# CommandLine internals

Small classes, mostly clear from their signatures. This is the thin "invariants & traps"
layer, the facts that are expensive to rediscover.

## Four roles, four classes

- **`Command`** is the definition: a tree whose root is the program and whose nodes are its
  commands. It knows nothing of `argv`, the environment or the output.
- **`Parser`** reads a command line against the tree and returns an immutable `Result`.
  It holds no state.
- **`HelpRenderer`** draws the help of any node, `render()` into a console and
  `renderToString()` into a string. Like `Parser`, it takes the node as an argument and keeps
  nothing between calls.
- **`Console`** writes to a stream and knows no `Command`. The renderer colors and writes
  through it; `renderToString()` without a console is plain text.

The parameters live in the `Parameters` namespace and split by **whether they take a value**,
the axis most settings follow. `Flag` takes none; `ValueParameter` holds the enum, the
normalizer and the real path, and `Option` and `Argument` extend it. What all of them share
lives in `Parameter`. **Every setting is a named argument of `add*()`**, passed on to the
constructor, and cannot change afterwards: it is a `readonly` property of the parameter. A setting
that makes no sense for a kind is not an argument of its method, so misusing it is an unknown
named argument, which static analysis reports. **`add*()` hand their arguments to the
constructor by `func_get_args()`**, which is positional, so the parameters of an `add*()` method
and of its constructor must keep the same order; swapping two of the same type breaks nothing
loudly. `Flag` and `Option` each keep their own alias, and `Command::getOptions()` returns both.
A subcommand exists only through `addCommand()`, knows its parent and cannot be moved.

Each node keeps an **ordered list of items**, `Flag`, `Option`, `Argument` and `Command`, in
the order the help shows them, and that list is its only model:
`getCommands()`, `getParameters()`, `getOptions()` and `getArguments()` filter it, a lookup by
name walks it, and the place of the node (`getRoot()`, `getPath()`, `getFullName()`) is derived
from the parent. Nothing is stored twice, so nothing can disagree. **What cannot change is a
`readonly` property, what grows with `add*()` is read through a method.**

## Checks of the definition

Everything is checked when it is added, because nothing added can change:

- The grammar of names, which follows only what the parser can read: an option name or alias
  is one or two dashes and a letter, digit or underscore, with no `=`, whitespace or control
  character anywhere; argument and command names start with a letter, digit or underscore and
  hold no whitespace or control character.
- A name, an alias or a negation is unique across the ancestors and the descendants of a node
  (siblings may share); arguments and subcommands exclude each other on one node.
- Required after optional, anything after a repeatable argument, and a required argument with
  a default value, judged against the arguments added before.

What concerns a parameter alone, the grammar of its names, a negation only of a long name and
the enum, is checked by its constructor, so a parameter the parser could not read cannot exist;
the grammar itself lives in `NameSyntax`. What involves the tree is checked by `Command`. The
name of a command is checked by `addCommand()`, not by the constructor, which also creates the
root and takes any name of the program.

The parameter is registered only after all its checks pass, so a refused `add*()` leaves the
node as it was. The parser therefore trusts the tree and checks no definition.

## parse() in three phases

The split exists because presence has to be recorded separately from value:

1. **`collect()`** reads the tokens into raw occurrences per name and **follows the command
   names down the tree**: on a node with subcommands, the first positional token picks one.
   It resolves aliases and bundles. An option used without a value yields the
   `OptionPresent = true` sentinel.
2. **`evaluate()`** converts what was actually supplied through `ValueParameter::normalize()`:
   the enum check on the raw string, then the case of a `BackedEnum`, then the normalizer. The
   parameter knows what a valid value is; the parser only keeps the sentinel away from it and
   turns its exception into a `ParseException`.
3. **`complete()`** fills in the names that did not occur, and demands required arguments.

Consequences worth knowing:

- **The line is always read from the root.** Given a subcommand, `parse()` only checks
  afterwards that the line ran it or a command below it.
- **Valid options are those of the selected node and all its ancestors**, looked up at
  parse time, so a root option added after `addCommand()` is inherited all the same. An
  option of another branch is reported as belonging to its command. The result holds only
  the parameters on the path to the selected node.
- **After `--` a command name is a plain value**, no command is selected any more.
- **A token that looks like a negative number is a value** unless an option of that name is
  valid at the node, so telling an option from a value needs the options of the node.
- **The negation of a flag, `--no-name`, is a name of its own**: it is indexed beside names
  and aliases, checked for conflicts like them, and occurs as `false`. A standalone flag
  answers only when its last occurrence is `true`.
- **A required command is demanded after the standalone flags are found**, so `--help` still
  answers a line that names no command.
- **Nothing asks `isset()` about a value while parsing**, so a normalizer may legitimately
  return `null` without the default overwriting it.
- **`valueOptional` says only what happens when the option is present**; the default value
  says what is returned when it is not. The default is returned as it is, never normalized
  or checked against the enum.
- **Only the last value of a parameter that is not repeatable is converted**, so an earlier
  bad value overridden by a later one is never reported.
  Normalizers run parameter by parameter, not in the order of the line, so one must not rely
  on another having run.
- **The sentinel is a public value.** A bare `--flag` parses as the literal `true`, and
  neither the enum check nor the normalizer sees it. An optional-value option used bare is
  therefore `true`, not its default. **An optional value is attached with `=`**; the next token
  is taken only when the option has an `enum` and the token is one of its values, so `--color never`
  works while nothing outside the enum, an argument or a command name, is ever swallowed.
- **An input wrong in more than one way reports the syntax problem, not the value problem**,
  because all the tokens are read before any value is converted. This cannot be reordered
  without losing the reason for the phases.
- An exception from `normalize()`, a value outside the enum or one a normalizer refused, is
  wrapped into a `ParseException` naming the parameter, with the original as `previous`, so all
  bad values read alike ("Option --x: expects …"). A `ParseException` passes through untouched and an
  `\Error` is not caught at all: a broken normalizer is a bug, not a bad value. The
  `catch (\Exception)` is deliberate, and the `dresscode:ignore` comment beside it must
  stand alone at the end of the line, since text after it is read as rule names.
- A path is resolved by `Normalizers::realPath()`, an ordinary normalizer. **The model
  describes a value only where another part reads it**: the enum stays a setting, because the
  help and the error message show its values, while a conversion nobody else reads is a closure.
- **`ParseException::$command` is never null**; it is the node where the line went wrong,
  so the application can print the help of exactly that command. A bad value is judged
  after the whole line is read, so it reports the selected node, even for an option
  inherited from above. Every exception the parser throws names a `ParseError` reason; one a
  normalizer throws itself passes through with the default reason `InvalidValue`.

**`standalone`** is answered between phase 1 and phase 2, so the command is already selected:
if such a flag of the selected path occurred, every other parameter of the path is its
default and phases 2 and 3 check, convert and demand nothing. The line still has to be a
line, because phase 1 has already run.

## Result

Reading an unknown name throws, so a typo cannot pass for a missing value. `offsetExists()`
deliberately answers like `isset()` on an array, **false for a known name whose value is
null**: consumers test flags with `isset($result['--fix'])`, and a naive "is the key
known" would silently make every such test true. The selected command is a property, never
a reserved key among the values. Presence survives into the result: `wasProvided()` answers
from the occurrences, never from the value.

## The help

`HelpRenderer` draws one node. **It never runs a normalizer, a callback or the parser**: the
help has to be printable before the application does any work. The colored output is the
plain one with escape sequences on top; stripping them gives the plain one back.

- Headings `Options:`, `Arguments:` and `Commands:` are added in front of every run of one kind.
- The usage always opens the help and the description of the node follows it, collapsed and
  wrapped like any other description; in the help of the parent the description stands beside
  the node in the list.
- Inherited options come last, under `Global options:` when the node has options of its
  own, otherwise under `Options:`.
- The syntax column is as wide as the longest syntax that **still fits** the limit; a longer
  one gets a line of its own and does not stretch the column for the rest. When fewer than
  `MinDescriptionWidth` columns stay for descriptions, every description goes below its syntax.
- Widths are measured by `Ansi::measure()`, so a colored help wraps like the plain one.

The help colors only through its own **roles**, which the private `HelpRenderer::Theme` maps to
the colors of `Console::color()`; `Console` knows no roles. Roles are chosen by conditions, so a
role missing from the map shows only when that branch is drawn in color, which the colored
snapshot `tests/snapshots/fluent.ansi.txt` does for all of them.

## Console: one stream, two questions about it

A console is **one stream** and everything it knows follows from that stream, so an application
writing to stdout and stderr makes one for each; a redirected stdout then cannot decide the
colors of stderr. Both answers can be given to the constructor, which is what an application
does for `--no-color` and a test for a memory stream.

`hasColors()` and `isTerminal()` are **two questions, not one**. Colors follow the terminal but
`NO_COLOR` (disables) and `FORCE_COLOR` (decides whatever the stream is) override it, so gate
*color* on the first and *interactive-only features* (a progress bar, line rewriting, a prompt) on
the second: a user may set `NO_COLOR` and still be on a real terminal.

- **The console writes what it is given.** `color()` is the only place that adds a sequence, and
  with colors off it adds none, so composed text comes out plain by itself. `write()` rewrites
  nothing, because it cannot tell text from the content of a file, and stripping the one would
  silently corrupt the other. A caller printing a text from elsewhere (the output of a
  subprocess) drops its colors with `Ansi::strip()`, which is the rare case and the visible one.
- `getWidth()` reads `COLUMNS` **every time**, which is how a narrow terminal is simulated in
  tests, and takes it only as a positive integer. Otherwise the terminal is asked only when the
  stream is one, so the help sent to STDERR follows STDERR; that answer is cached for the
  process, and without `exec()` the width is 80.

## Ansi: the single measure of width

`Ansi` is the one place that knows how wide text is on a terminal: an escape sequence takes no
column, a grapheme cluster one and a wide character (CJK, Hangul, fullwidth, emoji) two. The help,
a status and any column of an application measure through it, so a colored line wraps like the
plain one. `truncate()` keeps the escape sequences of what it drops, so a color it opened is still
closed, and with `keepEnd` it cuts the front, where a path is least interesting. **Nothing it
returns is ever wider than asked**: an ellipsis that would not fit is left out, which is what a
status cut to a narrow terminal depends on.

## Tests

The snapshots in `tests/snapshots` record the help of the program built by
`tests/fixtures/fluent.php` at three widths and in color, so a change of the layout shows as
a diff there rather than in the assertions of single tests.
