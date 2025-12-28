# CommandLine internals

Two small classes; most of it is clear from signatures. This is the thin
"conventions & traps" layer — the few facts that are expensive to rediscover.

## Help text is the schema (`addFromHelp`)

The clever, non-obvious part: `addFromHelp()` **parses formatted help text into
option definitions** with two regexes. The outer one extracts each option line and
its `(default: …)`; the inner one parses the option syntax itself, and its result
determines everything:

- `--long` / `-s, --long` → the option name is the **last** matched flag (`--long`),
  the alias the first (`-s`) when they differ;
- a value spec `<file>` (required), `[type]` (optional) or a bare word (`--width N`,
  required) → whether the option takes a value; a trailing `...` → **repeatable**;
  `<a|b|c>` → an **enum** (split on `|`);
- a `(default: …)` (or a `[…]` value spec) → the option is optional with that
  fallback.

The whole matched flag string must be fully consumed by the inner regex or it throws
`Unable to parse '…'`. The `$defaults` array passed alongside **merges over** the
parsed values (it supplies what help text can't express: `RealPath`, `Normalizer`,
`Enum`, `Repeatable`). `RealPath` is desugared into a `Normalizer` that resolves the
path (composing with any user normalizer). The `ValueType` (`None`/`Optional`/
`Required`) is then derived: no value spec → `None`; optional or has a default →
`Optional`; else `Required`.

## `parse()` and the `OptionPresent` sentinel

`parse()` walks the args with a **positional cursor** (`current()`/`next()` over the
positional options) alongside named-option lookup. A token starting with `-` is an
option, anything else is a positional argument — so **options and arguments may
interleave freely** (an argument can precede an option).

The trap is the sentinel `OptionPresent = true`. A `--flag` with no `=value` yields
this literal `true` as its "value". Then:

- if the option takes a value and the sentinel is still present, the **next token is
  consumed as the value only if it does not start with `-`**; otherwise a `Required`
  option throws and an `Optional` one keeps the `true`.
- so an optional-value option used bare (`--format`) parses as **`true`**, not its
  fallback. The **fallback is used only when the option is absent entirely** (the
  final fill-in pass). `enum` validation skips the `true` sentinel — but a
  `normalizer` does **not**: it receives the raw `true`. And fallbacks are assigned
  as-is, never normalized.

The fill-in pass at the end assigns fallbacks to unused non-`Required` options,
throws for a missing required **positional** argument (a missing required *option*
just becomes `null`), and casts a repeatable value to an array. A repeatable option
accumulates into a list; a non-repeatable one keeps the **last** occurrence.

## `parseOnly()` is a deliberately dumb early-exit path

`parseOnly(['--help', '--version'])` exists so info options work even when a required
argument is missing. It parses **only** the named options, **ignores everything
else, never validates, never throws**, and returns `null` for anything unused (but
respects aliases). Do not add validation to it — that would defeat the "`--help`
before the missing-argument error" use case.

## Console: two distinct terminal checks

`detectColors()` and `detectTerminal()` are **separate on purpose**. `detectTerminal`
is the pure CLI+TTY check (`stream_isatty(STDOUT)`); `detectColors` builds on it and
adds `NO_COLOR` (disables) and `FORCE_COLOR` (overrides the TTY check). The rule:
gate **color** on `detectColors`, but gate **interactive-only features** (progress
bars, line-rewriting, prompts) on `detectTerminal` — a user may set `NO_COLOR` yet
still be on a real terminal that should get a progress indicator. `color($color)`
with the string omitted emits the escape code with **no reset**, for building a
sequence manually (it is the null *string*, not a null color, that skips the reset).
