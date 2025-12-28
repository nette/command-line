# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Nette Command Line** is a lightweight PHP library providing two focused utilities:
- `Parser`: Command-line argument and option parsing with help text extraction
- `Console`: Terminal color output with intelligent capability detection

**Key characteristics:**
- Zero runtime dependencies (PHP 8.0-8.5 only)
- Minimal codebase (~274 LOC total)
- Part of the Nette Framework ecosystem
- Triple-licensed: BSD-3-Clause, GPL-2.0-only, GPL-3.0-only

## Essential Commands

### Development Setup
```bash
composer install              # Install dev dependencies
```

### Testing
```bash
composer run tester          # Run all tests with simple output
vendor/bin/tester tests -s -C   # Same as above
vendor/bin/tester tests -s -C # Run with code coverage
vendor/bin/tester tests/Parser.phpt -s -C  # Run specific test file
```

### Code Quality
```bash
composer run phpstan         # Run PHPStan static analysis (level 5)
```

### CI/CD Information
- Tests run on PHP 8.0, 8.1, 8.2, 8.3, 8.4, 8.5 via GitHub Actions
- Code coverage reports to Coveralls (PHP 8.1)
- Coding style checked via Nette Code Checker and Coding Standard
- PHPStan runs on master branch (informative only)

## Architecture

### Two-Class Design

**Parser (`src/CommandLine/Parser.php` - 209 lines)**
- Parses command-line arguments using help text as schema definition
- Extracts option definitions via regex from formatted help strings
- Supports: flags, arguments, aliases, default values, enums, file paths, custom normalizers
- Modern PascalCase constants (e.g., `Parser::Argument`, `Parser::Optional`)
- Deprecated UPPERCASE constants maintained for backward compatibility

**Console (`src/CommandLine/Console.php` - 65 lines)**
- ANSI color output with automatic terminal capability detection
- Respects `NO_COLOR` environment variable (https://no-color.org)
- Detects Windows VT100 support and TTY environments
- 16 named colors with foreground/background combinations

### Key Design Patterns

**Help Text as Schema:**
The Parser class uses regex to extract option definitions from formatted help text:

```php
$cmd = new Parser('
	-p, --param=<value>  Parameter description (default: 123)
	--verbose            Enable verbose mode
');
```

The help text format drives the parser configuration - options, arguments, defaults, and enums are all extracted from the help string structure.

**Option Configuration Array:**
Options can be configured via the second constructor parameter using constants:
- `Parser::Argument` - requires a value
- `Parser::Optional` - value is optional
- `Parser::Repeatable` - can be specified multiple times
- `Parser::Enum` - restricted to specific values
- `Parser::RealPath` - validates file path existence
- `Parser::Normalizer` - custom transformation function
- `Parser::Default` - default value when not specified

## Coding Standards

### Nette Framework Conventions
- Every file: `declare(strict_types=1)` at the top
- Tab indentation (not spaces)
- Comprehensive type hints on all parameters and return values
- PSR-12 inspired with Nette-specific modifications
- Minimal but clear documentation

### Naming Conventions
- PascalCase for class constants (modern style, e.g., `Parser::Optional`)
- camelCase for methods and properties
- Deprecated UPPERCASE constants aliased to PascalCase equivalents

### Documentation Style
- Classes: Brief description without unnecessary verbosity (e.g., "Stupid command line arguments parser")
- Methods: Document when adding value beyond type signatures
- Properties: Inline `@var` annotations for complex types

## Testing with Nette Tester

### Test File Structure
Files use `.phpt` extension and follow this pattern:

```php
<?php

declare(strict_types=1);

use Nette\CommandLine\Parser;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('description of what is tested', function () {
	$cmd = new Parser('...');
	Assert::same(['expected'], $cmd->parse(['input']));
});


test('another test case', function () {
	// Test implementation
});
```

### Testing Patterns
- Use `test()` function for each test case with clear descriptions
- No comments before `test()` calls - the description parameter serves this purpose
- Group related tests in the same file
- Use `Assert::same()` for exact equality checks
- Use `Assert::exception()` for exception testing

### Bootstrap Setup
All tests require `require __DIR__ . '/bootstrap.php';` which:
- Loads Composer autoloader
- Configures Tester environment
- Sets up test functions

## Version Management

**Current branch:** master (1.8-dev)
**PHP Support:** 8.0 minimum, tested through 8.5
**Branch alias:** dev-master → 1.8-dev

### Backward Compatibility
- Deprecated constants maintained with `@deprecated` annotations
- Old UPPERCASE constants aliased to new PascalCase versions
- Breaking changes noted in commit messages with "(BC break)"

## Common Development Scenarios

### Adding Parser Features
When extending the Parser class:
1. Add new constants in PascalCase format
2. Create UPPERCASE deprecated aliases if replacing old names
3. Update help text regex in constructor if needed
4. Add comprehensive tests in `tests/Parser.phpt`
5. Consider enum validation and normalizer patterns

### Console Color Additions
When modifying Console output:
1. Respect `$this->useColors` flag
2. Return plain strings when colors disabled
3. Test with `NO_COLOR` environment variable
4. Consider cross-platform compatibility (Windows VT100)

### Test Writing
1. Create or extend `.phpt` files in `tests/` directory
2. Use descriptive test names in `test()` function
3. Cover both success and error cases
4. Test edge cases (empty input, missing values, invalid formats)

## Dependencies and Tooling

**Runtime:** None (PHP 8.0+ only)

**Development:**
- `nette/tester` ^2.5 - Testing framework
- `tracy/tracy` ^2.9 - Debugging and error handling
- `phpstan/phpstan-nette` ^2.0 - Static analysis with Nette-specific rules

**Autoloading:**
- PSR-4: `Nette\` → `src/`
- Classmap fallback for `src/` directory
