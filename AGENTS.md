# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

Small classes, mostly clear from signatures; the value is a handful of traps - the
three phases of `parse()` with the command selection and the sentinel, `isset()` on
`Result`, and the two questions a console answers about its stream. Read
`docs/internals.md` before editing them.

## Project Overview

**Nette Command Line** is a tiny, zero-dependency library. `Command` defines a
command line as a tree of the program and its commands, `Parser` reads a command
line against it and returns a `Result`, `HelpRenderer` draws the help of any
command, `Console` writes to a stream in color and `Ansi` measures text the way a
terminal shows it. The parameters live in the
`Parameters` namespace: `Flag` extends `Parameter`, while `Option` and `Argument`
extend it through `ValueParameter`. `Section` and `Text` in the `Help` namespace
shape the help.

- **PHP Version**: 8.2 - 8.5
- **Package**: `nette/command-line`

## Essential Commands

```bash
# Run all tests
vendor/bin/tester tests -s        # or: composer tester
vendor/bin/tester tests/Parser.parse.phpt -s

# Static analysis (PHPStan level 8)
composer phpstan
```

## Conventions

- Every file starts with `declare(strict_types=1);`; **tabs**; everything typed;
  Nette Coding Standard.
- Constants and enum cases are modern PascalCase (`ParseError::UnknownOption`).
- Tests are Nette Tester `.phpt` under `tests/` (require `bootstrap.php`); use
  `test()` / `Assert::same` / `Assert::exception`, no comment before `test()`.

## Working in this repo

- **Definition, parsing, help and output are four classes.** `Command` never
  touches `argv`, the environment or a stream; `Parser` and `HelpRenderer` take
  the command as an argument and keep no state; `Console` knows no `Command`.
  Each node keeps one ordered list of items and the help is always drawn from it.
- **Every setting is a named argument of `add*()` and cannot change afterwards**, so
  everything is refused at once, the rules over several arguments included, and the
  parser checks no definition. A setting that makes no sense for a kind of parameter
  is not an argument of its `add*()` method.
- **`parse()` has three phases**: collect the raw occurrences while following the
  command names down the tree, convert what was supplied, fill in what was not.
  The line is always read from the root and the valid options are those of the
  selected node and its ancestors. Presence is recorded separately from value, so
  a normalizer may return `null`; an optional value and the default value are
  independent.
- **A bare `--flag` yields the literal `true` sentinel**, which neither the enum
  check nor the normalizer sees. An optional-value option used bare is therefore
  `true`, not its default; the default applies only when the option is absent.
- **`standalone` answers between phase 1 and 2**, after the command is selected:
  the flag is `true`, everything else is its default, nothing is validated or
  converted.
- **`isset()` on `Result` is false for a known name with `null`**, like on an
  array; reading an unknown name throws. Consumers rely on both.
- **A `Console` is one stream**, and its colors, width and terminal follow that stream;
  an application writing to stdout and stderr makes one for each. `hasColors()` and
  `isTerminal()` are separate on purpose: gate *color* on the first (it honors
  `NO_COLOR`/`FORCE_COLOR`), *interactive-only* features (a progress bar, a prompt) on the
  second, since a user may disable color yet still be on a real terminal. The help
  colors only through its own roles, which `HelpRenderer::Theme` maps to colors;
  `Console` knows no roles.
- User-facing how-to (`addFlag`/`addOption`/`addArgument` and their settings, the help-text
  format, color codes) is manual material and lives in the public web docs, not here.
