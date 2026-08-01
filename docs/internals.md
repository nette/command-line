# CommandLine internals

Small classes, mostly clear from their signatures. This is the thin "invariants & traps"
layer, the facts that are expensive to rediscover.

## Three roles, three classes

- **`Command`** is the definition: a tree whose root is the program and whose nodes are its
  commands. It knows nothing of `argv`, the environment or the output.
- **`Parser`** reads a command line against the tree and returns an immutable `Result`.
  It holds no state.
- **`Console`** colors the output and knows no `Command`.

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
the order they were added, and that list is its only model:
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
- A name or an alias is unique across the ancestors and the descendants of a node
  (siblings may share); arguments and subcommands exclude each other on one node.
- Required after optional, anything after a repeatable argument, and a required argument with
  a default value, judged against the arguments added before.

What concerns a parameter alone, the grammar of its names and the enum, is checked by its
constructor, so a parameter the parser could not read cannot exist;
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
- **The model describes a value only where another part reads it**: the enum stays a setting,
  because the error message shows its values, while a conversion nobody else reads is a closure.
- **`ParseException::$command` is never null**; it is the node where the line went wrong,
  so the application can print the help of exactly that command. A bad value is judged
  after the whole line is read, so it reports the selected node, even for an option
  inherited from above. Every exception the parser throws names a `ParseError` reason; one a
  normalizer throws itself passes through with the default reason `InvalidValue`.

## Result

Reading an unknown name throws, so a typo cannot pass for a missing value. `offsetExists()`
deliberately answers like `isset()` on an array, **false for a known name whose value is
null**: consumers test flags with `isset($result['--fix'])`, and a naive "is the key
known" would silently make every such test true. The selected command is a property, never
a reserved key among the values. Presence survives into the result: `wasProvided()` answers
from the occurrences, never from the value.

## Console: two distinct terminal checks

`detectColors()` and `detectTerminal()` are **separate on purpose**. `detectTerminal` is the
pure CLI and TTY check; `detectColors` builds on it and adds `NO_COLOR` (disables) and
`FORCE_COLOR` (overrides the TTY check). Gate **color** on `detectColors`, but gate
**interactive-only features** (progress bars, line rewriting, prompts) on `detectTerminal`,
because a user may set `NO_COLOR` and still be on a real terminal.

`color($color)` with the string omitted emits the escape code with **no reset**; it is the
null *string* that skips the reset, not a null color.
