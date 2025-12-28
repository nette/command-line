# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works
internally and the rationale behind key design decisions - lives in `docs/`.
Consult it before non-trivial changes; it is the source of truth from which the
public manual is distilled.

Two small classes, mostly clear from signatures; the value is a few traps - the
help-text-as-schema parser, the `parse()` sentinel, and the two terminal checks.
Read `docs/internals.md` before editing them.

## Project Overview

**Nette Command Line** is a tiny, zero-dependency library with two utilities:
`Parser` (argument/option parsing, including help-text-driven definitions) and
`Console` (terminal color output with capability detection).

- **PHP Version**: 8.2 - 8.5
- **Package**: `nette/command-line`

## Essential Commands

```bash
# Run all tests
vendor/bin/tester tests -s        # or: composer tester
vendor/bin/tester tests/Parser.fluent.phpt -s

# Static analysis (PHPStan level 8)
composer phpstan
```

## Conventions

- Every file starts with `declare(strict_types=1);`; **tabs**; everything typed;
  Nette Coding Standard.
- Constants are modern PascalCase (`Parser::Optional`) with deprecated UPPERCASE
  aliases kept for BC.
- Tests are Nette Tester `.phpt` under `tests/` (require `bootstrap.php`); use
  `test()` / `Assert::same` / `Assert::exception`, no comment before `test()`.

## Working in this repo

- **Help text *is* the schema.** `addFromHelp()` parses formatted help with two
  regexes: the option name is the **last** flag on a line, the alias the first; a
  `<file>`/`[type]` spec sets required/optional, `...` marks repeatable, `<a|b|c>` an
  enum. The `$defaults` array merges over the parsed result (it supplies `RealPath`,
  `Normalizer`, etc.); `RealPath` desugars into a `Normalizer`.
- **`parse()` uses an `OptionPresent = true` sentinel.** A bare `--flag` yields the
  literal `true`; a value is taken from the next token only if it doesn't start with
  `-`. So an optional-value option used bare parses as **`true`, not its fallback** -
  the **fallback applies only when the option is absent entirely**. A missing required
  *positional argument* throws; a missing required *option* becomes `null`.
- **`parseOnly()` is deliberately dumb** - it parses only the named options, never
  validates, never throws (so `--help`/`--version` work despite a missing required
  argument). Don't add validation to it.
- **`Console::detectColors()` and `detectTerminal()` are separate on purpose.** Gate
  *color* on `detectColors` (honors `NO_COLOR`/`FORCE_COLOR`), but gate
  *interactive-only* features (progress bars, prompts) on `detectTerminal` (pure TTY)
  - a user may disable color yet still be on a real terminal.
- User-facing how-to (fluent `addSwitch`/`addOption`/`addArgument`, the help-text
  format, color codes) is manual material and lives in the public web docs, not here.
