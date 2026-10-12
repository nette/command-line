# CommandLine internals

Small classes, mostly clear from their signatures. This is the thin "invariants & traps"
layer, the facts that are expensive to rediscover.

## Four roles, four classes

- **`Command`** is the definition: a tree whose root is the program and whose nodes are its
  commands. It knows nothing of `argv`, the environment or the output.
- **`Parser`** reads a command line against the tree and returns an immutable `ParseResult`.
  It holds no state.
- **`HelpRenderer`** draws the help of any node, `render()` into a console and
  `renderToString()` into a string, and `renderError()` a usage error followed by that help.
  Like `Parser`, it takes the node as an argument and keeps nothing between calls.
- **`Console`** writes to a stream and knows no `Command`. The renderer colors and writes
  through it; `renderToString()` without a console is plain text.

The parameters live in the `Parameters` namespace and split by **whether they take a value**,
the axis most settings follow. `Flag` takes none; `ValueParameter` holds the enum and the
normalizer, and `Option` and `Argument` extend it. What all of them share lives in `Parameter`.
**Every setting is a named argument of `add*()`**, passed on to the
constructor, and cannot change afterwards: it is a `readonly` property of the parameter. A setting
that makes no sense for a kind is not an argument of its method, so misusing it is an unknown
named argument, which static analysis reports. **`add*()` hand their arguments to the
constructor by `func_get_args()`**, which is positional, so the parameters of an `add*()` method
and of its constructor must keep the same order; swapping two of the same type breaks nothing
loudly. `Flag` and `Option` each keep their own alias, and `Command::getOptions()` returns both.
A subcommand exists only through `addCommand()` or `addCommandFrom()`, knows its parent and
cannot be moved; a node cannot be cloned either, since the copy would share children that know
the original.

Each node keeps an **ordered list of items**, `Flag`, `Option`, `Argument`, `Command`,
`Section` and `Text`, in the order the help shows them, and that list is its only model:
`getCommands()`, `getParameters()`, `getOptions()` and `getArguments()` filter it, a lookup by
name walks it, and the place of the node (`getRoot()`, `getPath()`, `getFullName()`) is derived
from the parent. Nothing is stored twice, so nothing can disagree. **What cannot change is a
`readonly` property, what grows with `add*()` is read through a method.** The usage is not an
item but a property of the node, given when the node is created.

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

What concerns a parameter alone, the grammar of its names, a negation only of a long name, an
alias other than its name or negation, and the enum, is checked by its constructor, so a
parameter the parser could not read cannot exist; the grammar itself lives in `NameSyntax`. What
involves the tree is checked by `Command`. The name of a command is checked when `addCommand()` or
`addCommandFrom()` attaches it, not by the constructor, which also creates the root and takes any
name of the program.

The parameter is registered only after all its checks pass, so a refused `add*()` leaves the
node as it was. The parser therefore trusts the tree and checks no definition, with one
exception: the binding of handlers, below.

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
  option of another branch is reported as belonging to its commands, all of them; a hidden
  option is never named there nor suggested, it is simply unknown. The result holds only the
  parameters on the path to the selected node.
- **After `--` a command name is a plain value**, no command is selected any more.
- **A token that looks like a negative number is a value** (`-5`, but also `-2h`, which the
  normalizer judges) unless an option valid at the node is named like a number: then, as in
  argparse, every `-5` is an option or a bundle, and a negative value takes `=` or follows `--`.
  `-.5` is a value always, since no option name starts with a dot. Telling an option from a value therefore needs the options of the node, and changes
  when a command is selected. Where a command is expected, such a token is an unknown option.
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
  works while nothing outside the enum is ever swallowed. A token equal to a value is taken even
  where it would be an argument or a command; the collision with a command is refused by the
  definition (`addOption()` against the commands below, `addCommand()` against the options on the
  path), the one with an argument cannot be known in advance.
- **An input wrong in more than one way reports the syntax problem, not the value problem**,
  because all the tokens are read before any value is converted. This cannot be reordered
  without losing the reason for the phases.
- An exception from `normalize()`, a value outside the enum or one a normalizer refused, is
  wrapped into a `ParseException` naming the parameter, with the original as `previous`, so all
  bad values read alike ("Option `--x`: expects …"). A `ParseException` passes through untouched and an
  `\Error` is not caught at all: a broken normalizer is a bug, not a bad value. The
  `catch (\Exception)` is deliberate, and the `dresscode:ignore` comment beside it must
  stand alone at the end of the line, since text after it is read as rule names.
- **A ready-made normalizer takes the value itself**, never makes a closure: `Normalizers::int()`,
  `float()` and `realPath()` are static methods, so that an attribute names one as
  `[Normalizers::class, 'int']`, which every PHP accepts there, while a call of a factory is accepted
  by none and `Normalizers::int(...)` only from PHP 8.5 on. A range is
  further parameters, passed by a closure or, from PHP 8.6 on, by a partial application
  (`Normalizers::int(?, min: 1)`). Since the range is only seen with a value, one that no number fits
  throws a `ValueError`: an `\Error` passes the parser unwrapped, as a bug of the definition, not as
  a bad value. A path is resolved by `realPath()`, an ordinary normalizer. **The model
  describes a value only where another part reads it**: the enum stays a setting, because the
  help and the error message show its values, while a conversion nobody else reads is a closure.
- **`ParseException::$command` is never null**; it is the node where the line went wrong,
  so the application can print the help of exactly that command. A bad value is judged
  after the whole line is read, so it reports the selected node, even for an option
  inherited from above. Every exception the parser throws names a `ParseFailure` reason, and
  `token` the raw part of the line as given (the name without its value, the value itself when
  the value is bad, a letter of a bundle on its own), null for what is missing; one a normalizer throws itself passes through with the
  default reason `InvalidValue` and no token.
- **`ParseException` extends `UsageException`**, which a handler throws for what the definition
  cannot express (inputs that conflict, a value that does not fit the context), so an application
  catches one class and draws both alike by `renderError()`. The command of either is required.
- **Messages write code in backticks**, as descriptions do: names, commands and the values given,
  each through `HelpRenderer::formatCode()`, which also escapes control characters, since a code
  span does not cross a line, and writes a value holding a backtick in a longer run of them;
  `formatText()` and `splitCodeSpans()` read it back.

**`standalone`** is answered between phase 1 and phase 2, so the command is already selected:
if such a flag of the selected path occurred, the other flags are taken as they occurred
(literals `true` and `false`, nothing to convert), every other parameter of the path is its
default and phases 2 and 3 check, convert and demand nothing. So `--help --no-color` can draw
the help plain, while an option with a value or an argument keeps its default, though
`wasProvided()` says it was given. The line still has to be a line, because phase 1 has already run.

## ParseResult

**`get()` throws for an unknown name**, so a typo cannot pass for a missing value, not even
behind `??`: in `get('--format') ?? 'console'` only a known name whose value is null falls
back. That is why the result is not `ArrayAccess`, whose `isset()` and `??` would either answer
for an unknown name silently or throw where PHP never does. The fallback does not catch the
sentinel, so a bare `--format` with an optional value still gives `true`. The selected command
is a property, never a reserved key among the values. Presence survives into the result:
`wasProvided()` answers from the occurrences, never from the value.

**A numeric name is an integer key.** An argument `'0'` or an option `-1` is stored by PHP as
the key `0` or `-1`, so anything comparing names strictly has to go through `isset()` or cast to
string. `foreach` gives the name as a string, `toArray()` and `iterator_to_array()` as the
integer, which `get()` takes only cast to string, and spreading renumbers it, so
`[...$result]` loses such a name.

## Handlers

A node may carry a `handler`, a `\Closure` the definition only holds: neither the parser nor
the help reads it, and **only `ParseResult::run()` calls it**. `run()` refuses a result in which
a standalone flag answered, since its arguments were neither demanded nor converted and the
handler would run instead of the help, and a node without a handler, which is a valid node
(a group of commands, a manual dispatch), only not a runnable one.

`HandlerBinding` gives each parameter of the handler its value by one rule: a parameter typed
`ParseResult` or `Command` gets the result or the selected node, any other the input of the path
whose name is derived from the variable, `$maxWarnings` the option `--max-warnings` or the
argument `max-warnings`. **The name is derived one way only**, from the variable; an input whose
name no variable gives (`-v`, `--a.b`, `--maxWarnings`) is read from the result. An option and an
argument of one derived name are refused only when a parameter refers to them.

**The types are checked before anything runs**: a parameter has to accept every value its input
can give, which `describeValues()` lists from the definition (a flag `true`, `false` when negatable;
a value its normalizer's declared return type, the case of its enum class or a string; `true` of an
optional value; the default when absent, unless the argument is required; a repeatable input an
array). A normalizer without a declared return type cannot be checked and is refused once a typed
parameter binds to it. Element types of an array are not checked, PHP has none at runtime.
`accepts()` judges as a strict call does: `int` fits `float`, a class fits its ancestors, `object` takes
only a class, `iterable` an array or a `Traversable`, `callable` a class with `__invoke()`, `Closure`
included; a value declared as `iterable`, `callable` or `object` fits only the same declaration, since
it may be an array, a string or any object.

What the signature says on its own (variadic, by reference, a default that the input would always
override, a return type other than an exit code) is refused when the handler is given. The binding
depends on the tree, and an input may be added after the handler, so **`parse()` checks the whole
tree** before reading the line, every time and without caching, since a node can still grow after
a parse. It is the only definition the parser checks. The binding is a list of resolvers, one function
of the result per parameter, which `run()` builds again and calls, so an input added between `parse()`
and `run()` is checked as well.

**Attributes are another way to write the same tree**, not a model of their own, and **the call says
which way is used**: `addCommandFrom($handler)` describes the node by the attributes of the handler,
and `Command::from($handler)` the program the same way. A handler given to `addCommand()` or the
constructor by `handler:` is only bound, and an attribute of the library on it is refused, so that it
is never silently skipped.
`#[Command]` gives the description, usage and `commandRequired`, the name is derived from the method by
the same rule as the name of an input (`migrateSuppressions()` is `migrate-suppressions`), and a closure
or `__invoke()`, whose name says nothing of the command, gives it by `name:`. A method without
`#[Command]` given to `addCommandFrom()` is a command named after it, without a description;
`#[Command]` is required only to give what cannot be derived, and as the mark `addCommandsFrom()` looks
for. **The program is never named after its handler**: the method of a tool of one level (`run()`,
`deploy()`) names an action, not the binary, so `Command::from()` takes the name from `name:` alone and
without it the help names the script, as for any root without a name.

Every parameter with `#[Flag]`, `#[Option]` or `#[Argument]` is turned into a call of its `add*()`
method. All four attributes take the settings of their `add*()` method without the name, the default
and the handler, with `name:` last to override the derived one; a test compares the signatures, so a new
setting of `add*()` cannot be forgotten in the attribute. The name is derived from the parameter as
above, the default is the default value of the parameter (an object other than an enum case is
refused, it would be shared by every run), an argument is optional when the parameter has a default
value (nullable is not optional: a normalizer may return null for a given value), and a parameter
typed by a backed enum takes its cases. `repeatable` is never derived, since an array may be one value
of a normalizer. A parameter with an attribute binds to the input it defined, by that input's name. A
normalizer in an attribute may be a static method as `[class, name]`, which every PHP accepts in a
constant expression, where a call is accepted by none; an instance method or a missing one is an
error of the definition naming the parameter.

`Command::from()` and `addCommandFrom()` read the attributes, creating the node without the
constructor, which refuses them, so the node gets the inputs of its handler before it is attached;
`attach()` checks the name and the options against the path and only attaches a node that passed,
so a refused handler leaves the tree as it was. An attribute of the library on the wrong place (an
input attribute on the function, `#[Command]` on a parameter, two input attributes on one
parameter, one on a parameter given by its type) is refused by name, never skipped.

**An object of options is a parameter typed by a class whose constructor declares inputs**; it needs no
marker, the attributes of its own constructor are the marker. A class whose constructor only takes
another object of options is therefore no object of options and binds by name like any parameter; the
nested object has to sit in a constructor that declares inputs of its own. Every step treats the
constructor as it treats the handler: the checks of the signature, the definition of the inputs at the
place of the parameter, the binding and the types, recursively, and the call creates it from the bound
values with `newInstance()`. A class that cannot be created (abstract, a constructor that is not
public) is refused by the definition, and so is one that contains itself, directly or through another,
which the checks of the signature find by the classes on the way down, before any other step recurses.
Being a parameter like any other, an object of options is read only when the handler describes its
command, and refused with `handler:`. `addCommandsFrom()` adds the commands of the public methods
with `#[Command]` and refuses one that is not public, which the library would call past its visibility;
a private handler is handed over by the class itself through `addCommandFrom()`. It goes in the order
reflection lists them, the own methods in the order of their declaration and the inherited ones after
them; each one is atomic, the whole call is not.

The handler is called directly from a strict file, `$handler(...$arguments)`;
`ReflectionFunction::invokeArgs()` would coerce `'2'` into an `int` parameter, which is the
conversion the normalizers own. An `int` is returned as the exit code, `null` as 0, anything else
is a `LogicException`; exceptions of the handler pass through.

## The help

`HelpRenderer` draws one node. **It never runs a normalizer, a callback or the parser**: the
help has to be printable before the application does any work. The colored output is the
plain one with escape sequences on top; stripping them gives the plain one back, only without
the backticks of the code a description or a text writes as a code span of Markdown. The color
takes their place, and the plain help keeps them, since a pipe or an agent reading it has no
other way to tell the code from the words; wrapping measures what is printed, so the two may
break a line in different places.

- Headings `Options:`, `Arguments:` and `Commands:` are added in front of every run of one
  kind, and only when the author wrote no heading at all; one of their own means they
  arrange the help.
- The usage, generated or written by hand, always opens the help and the description of the
  node follows it, collapsed and wrapped like any other description; in the help of the parent
  the description stands beside the node in the list.
- Inherited options come last, under `Global options:` when the node has options of its
  own, otherwise under `Options:`.
- Hidden parameters appear nowhere, not even in the generated usage. A required hidden
  argument is allowed (a worker run by the program itself); the generated usage then cannot be
  run as shown, and a missing one is named by the message.
- The width wraps descriptions only; the usage, a text, a syntax or a single word longer than
  the width overflows rather than being cut.
- The syntax column is as wide as the longest syntax that **still fits** the limit; a longer
  one gets a line of its own and does not stretch the column for the rest. When fewer than
  `MinDescriptionWidth` columns stay for descriptions, every description goes below its syntax.
- Widths are measured by `Ansi::measure()`, so a colored help wraps like the plain one.
- **A description is wrapped as words with roles and colored after**, a run of one role per
  line, so a color never spans two lines. The default is one such run, so a `)` inside it or a
  "(default: …)" written in the description changes nothing. A default with no words for it (a
  blank string, a bool, an array) is not shown.

The help colors only through its own **roles**, which the private `HelpRenderer::Theme` maps to
the colors of `Console::color()`; `Console` knows no roles. Roles are chosen by conditions, so a
role missing from the map shows only when that branch is drawn in color, which the colored
snapshot `tests/snapshots/fluent.ansi.txt` does for all of them but `code` and `error`, drawn in
color by `tests/HelpRenderer.phpt`.

## Console: one stream, two questions about it

A console is **one stream** and everything it knows follows from that stream, so an application
writing to stdout and stderr makes one for each; a redirected stdout then cannot decide the
colors of stderr. Both answers can be given to the constructor, which is what an application
does for `--no-color` and a test for a memory stream.

`hasColors()` and `isTerminal()` are **two questions, not one**. Colors follow the terminal but
`NO_COLOR` (disables) and `FORCE_COLOR` (decides whatever the stream is) override it, `NO_COLOR`
winning when both are set; outside the CLI there are no colors at all, so that no sequence reaches a
page. Gate *color* on the first and *interactive-only features* (a status, line rewriting, a prompt)
on the second: a user may set `NO_COLOR` and still be on a real terminal.

How many colors is **one value, the `ColorDepth`**, not a switch per feature: two flags (colors,
true color) would allow true color without colors. Its cases are the levels of `FORCE_COLOR`, which
gives the least depth, while the terminal gives `Ansi256` and `COLORTERM` `TrueColor`; the higher
wins, as in supports-color. The cases are named as termenv names its profiles, by the count of colors.

**`Color` draws one color at a depth**, apart from the console, which only knows the depth and the
grammar `'foreground/background'`. Every color has its RGB; a name also has the SGR code the theme
draws, an index its number of the palette, so there are no three nullable forms to branch on. A
color the theme does not decide (a hex, an index) is lowered when drawn: a hex to the nearest point
of the cube or the grays (the 16 colors of the terminal are left out there, their look being the
theme's), anything to one of the 16 at `Ansi16`. There the **hue decides, not the distance of RGB**:
a pastel is nearer to gray than to any saturated name, so by distance a whole light theme would turn
gray. A hue gets the name of the nearest hue, dark or bright by its lightness, and only a color with
little spread of its components is a gray. `Color::Names` holds the SGR code and the RGB xterm gives
each name in one table, and the RGB of an index is computed, so there is no second table to keep in
step.

- **The console writes what it is given.** `color()` and `link()` are the only places that add a
  sequence, and with colors off they add none, so composed text comes out plain by itself. `write()` rewrites
  nothing, because it cannot tell text from the content of a file, and stripping the one would
  silently corrupt the other. A caller printing a text from elsewhere (the output of a
  subprocess) drops its colors with `Ansi::strip()`, which is the rare case and the visible one.
- **`color()` refuses an unknown name even when colors are off**, so a typo cannot wait for a
  terminal to show up. A `null` color is no color at all, which spares the caller a branch.
- **`link()` needs a terminal and colors.** No terminal tells whether it takes OSC 8, so colors stand
  for it, and one that does not drops the sequence and shows the text. A pipe with forced colors gets
  no link, since nobody clicks in a log and a viewer that does not know OSC 8 would show it as junk.
  Otherwise a text other than the URL is followed by the URL in parentheses, so a log or a pipe
  never loses where the link led. **A URL with a control character is refused** always, since ESC
  or BEL would end the link and start a sequence of its own; the bytes above ASCII are
  percent-encoded inside the link only, as OSC 8 takes 32-126. The text is the caller's, as in
  `write()`.
- **`setStatus()` owns the drawing in place.** It splits the lines at every newline, expands
  tabs to the stops of 8 (exact, since a line starts in the first column), cuts every line to the
  width (a wrapped line could not be redrawn), hides the cursor, and leaves it at the first line
  of the status; `write()` and `clearStatus()` then erase from there down with `\e[J`, so the
  console keeps no height or width of its own. The next `setStatus()` draws below what was
  written meanwhile. Other control characters and moves of the cursor are the caller's risk.
- **A status lives as long as its console.** The destructor erases it and shows the cursor, so a
  temporary `(new Console)->setStatus()` is gone at once. A fatal error runs no destructor, so a
  shutdown function does the same; it holds the console only by a `WeakReference` and is a
  `static` closure, or it would keep every console alive. Ctrl+C ends PHP with neither, which only
  the application can catch.
- **A status takes whole lines**, so one drawn after output that did not end with a newline opens
  a line of its own; erasing it would otherwise take that half-written line with it. Therefore
  `write()` remembers whether the text ended with a newline, which is the cheap answer and errs
  on the safe side: a text of escape sequences alone only costs an empty line. Nothing to draw
  (`''` or `[]`) erases the status instead of drawing an empty one.
- **A status knows only its own console.** Two consoles over stdout and stderr are two objects on
  one terminal, so writing to the one erases nothing drawn by the other. Draw the status on the
  console the run also writes its errors to, or a warning printed meanwhile lands over the status.
- `getWidth()` reads `COLUMNS` **every time**, which is how a narrow terminal is simulated in
  tests, and takes it only as a positive integer. Otherwise the terminal is asked only when the
  stream is one, so the help sent to STDERR follows STDERR. The console asks the terminal once
  and keeps the answer, so a resized terminal needs `COLUMNS` or a new console. On Unix it asks
  `stty` about `/dev/tty`, the terminal of the process, since `exec()` hands the child the stdin,
  which may be a pipe; without `exec()` or a controlling terminal the width is 80.

## Prompt: a question has two ends

`Prompt` is a class of its own, not a method of `Console`, because a question has two streams, the
console it writes to and the input it reads from, while a console is one.

- **It asks only when both ends are a terminal** and refuses with a `LogicException` before writing
  or reading, so an answer is never taken from a pipe nobody watches; `inputTerminal:` overrides
  only the input, a test makes the console a terminal by its own constructor.
- **The end of the input is `null`, never the default**, and ends the question, so a closed input
  cannot loop; an empty line is the default, without one it asks again, as does an unknown answer.
- The terminal echoes the answer with its newline, which the console does not see, so the prompt
  tells it by the internal `Console::markLineStart()`; otherwise the next status would open an
  empty line.
- It owns no signal, no `exit()`, no `/dev/tty` and no mode of the terminal; those stay with the
  application.

## Ansi: the single measure of width

`Ansi` is the one place that knows how wide text is on a terminal: an escape sequence takes no
column, a grapheme cluster one and a wide character (CJK, Hangul, fullwidth, emoji) two. The help,
a status and any column of an application measure through it, so a colored line wraps like the
plain one. `truncate()` keeps the escape sequences of what it drops, so a color it opened is still
closed, and with `keepEnd` it cuts the front, where a path is least interesting. **Nothing it
returns is ever wider than asked**: an ellipsis that would not fit is left out, which is what a
status cut to a narrow terminal depends on.

- **Where unsure, it measures wider, never narrower**, since a line measured too short overflows
  and breaks the redrawing of a status. A cluster is wide when it holds a wide character or the
  emoji presentation selector (`☝🏽`, `❤️`), and takes no column when it holds only marks, format
  and control characters. East Asian Ambiguous characters are narrow, as in wcwidth. A cluster is
  measured as a terminal that knows grapheme clusters draws it; one that draws the characters of
  a ZWJ sequence apart shows it wider.
- **The wide characters are a generated table**, `Ansi::WideCharacters`, rewritten from
  `EastAsianWidth.txt` by `tools/generate-width-table.php`; a hand-made list would miss that
  narrow symbols such as `⚠` stand between wide ones. The grapheme clusters come from the PCRE
  of PHP, whose Unicode may be older than the table; that errs wider too.
- **Control characters are outside the contract** and take no column, a tab included.
  `truncate()` keeps only the escape sequences of what it drops, not control characters, so a
  backspace cannot overwrite the ellipsis.
- `strip()` removes every escape sequence in the 7-bit form, one left unterminated ending at the
  first byte that does not belong to it, at the latest at the next ESC, and nothing else; it is
  no sanitizer of untrusted text.

## Tests

The snapshots in `tests/snapshots` record the help of the program built by
`tests/fixtures/fluent.php` at three widths and in color, so a change of the layout shows as
a diff there rather than in the assertions of single tests.
