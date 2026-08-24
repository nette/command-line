<?php declare(strict_types=1);

use Nette\CommandLine\{Ansi, ColorDepth, Console};
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$console = new Console(colorDepth: ColorDepth::Ansi256, terminal: true);

Assert::same("\e]8;;https://nette.org\e\\https://nette.org\e]8;;\e\\", $console->link('https://nette.org'));
Assert::same("\e]8;;https://nette.org\e\\Nette\e]8;;\e\\", $console->link('https://nette.org', 'Nette'));
Assert::same(5, Ansi::measure($console->link('https://nette.org', 'Nette'))); // the URL takes no column
Assert::same('Nette', Ansi::strip($console->link('https://nette.org', 'Nette')));
Assert::same("\e[90m\e]8;;https://nette.org\e\\Nette\e]8;;\e\\\e[0m", $console->color('gray', $console->link('https://nette.org', 'Nette')));

$plain = new Console(colorDepth: ColorDepth::None);
Assert::same('https://nette.org', $plain->link('https://nette.org'));
Assert::same('https://nette.org', $plain->link('https://nette.org', 'https://nette.org'));
Assert::same('Nette (https://nette.org)', $plain->link('https://nette.org', 'Nette')); // the URL is not lost

$forced = new Console(colorDepth: ColorDepth::Ansi256, terminal: false); // FORCE_COLOR into a log, where nobody clicks
Assert::same('Nette (https://nette.org)', $forced->link('https://nette.org', 'Nette'));

// a non-ASCII URL is encoded only inside the link, the text shows it readable
Assert::same("\e]8;;https://nette.org/%C5%99\e\\https://nette.org/ř\e]8;;\e\\", $console->link('https://nette.org/ř'));
Assert::same('https://nette.org/ř', $plain->link('https://nette.org/ř'));
Assert::same('Nette (https://nette.org/ř)', $plain->link('https://nette.org/ř', 'Nette'));

// a control character could end the link and start a sequence of its own, so it is refused even without a terminal
foreach (["https://x/\e\\\e[2J", "https://x/\x07", "https://x/\n", "https://x/\u{9C}"] as $url) {
	Assert::exception(fn() => $plain->link($url), InvalidArgumentException::class, "The URL '%a%' holds a control character.");
}

Assert::exception( // the message does not pass the character on
	fn() => $plain->link("https://x/\u{9C}"),
	InvalidArgumentException::class,
	"The URL 'https://x/\\302\\234' holds a control character.",
);
