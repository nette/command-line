# Upgrading nette/command-line

## 1.9.0

- **Removed** `Nette\CommandLine\Parser::checkArg()`: no replacement, the parser applies the enum check and the normalizer itself (d6edbb89)
- **Renamed** `Nette\CommandLine\Parser::ARGUMENT` → `Nette\CommandLine\Parser::Argument`, using the old name triggers E_USER_DEPRECATED on PHP 8.4+ (deprecated since 1.7) (45e463d9)
- **Renamed** `Nette\CommandLine\Parser::OPTIONAL` → `Nette\CommandLine\Parser::Optional`, using the old name triggers E_USER_DEPRECATED on PHP 8.4+ (deprecated since 1.7) (45e463d9)
- **Renamed** `Nette\CommandLine\Parser::REPEATABLE` → `Nette\CommandLine\Parser::Repeatable`, using the old name triggers E_USER_DEPRECATED on PHP 8.4+ (deprecated since 1.7) (45e463d9)
- **Renamed** `Nette\CommandLine\Parser::ENUM` → `Nette\CommandLine\Parser::Enum`, using the old name triggers E_USER_DEPRECATED on PHP 8.4+ (deprecated since 1.7) (45e463d9)
- **Renamed** `Nette\CommandLine\Parser::REALPATH` → `Nette\CommandLine\Parser::RealPath`, using the old name triggers E_USER_DEPRECATED on PHP 8.4+ (deprecated since 1.7) (45e463d9)
- **Renamed** `Nette\CommandLine\Parser::VALUE` → `Nette\CommandLine\Parser::Default`, using the old name triggers E_USER_DEPRECATED on PHP 8.4+ (deprecated since 1.7) (45e463d9)
- **Changed** `Nette\CommandLine\Parser::parse()` now checks the enum on the raw value, before the normalizer; previously it checked the normalized value (the first element of an array); list the raw values in the enum if your normalizer changed them (6908f00c)
