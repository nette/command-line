<?php declare(strict_types=1);

/**
 * Regenerates Ansi::WideCharacters, the characters a terminal gives two columns, from EastAsianWidth.txt
 * of the given version of Unicode: the Wide and Fullwidth ones except combining marks, plus
 * the regional indicators, which pair into a flag.
 *
 * Usage: php tools/generate-width-table.php 17.0.0
 */

$version = $argv[1] ?? fail('Usage: php tools/generate-width-table.php <unicode version>');
$data = file_get_contents("https://www.unicode.org/Public/$version/ucd/EastAsianWidth.txt")
	?: fail("Cannot download EastAsianWidth.txt of Unicode $version.");

$ranges = [[0x1F1E6, 0x1F1FF]]; // regional indicators
preg_match_all('~^([0-9A-F]+)(?:\.\.([0-9A-F]+))?\s*;\s*[WF]\s*#\s*(\w\w)~m', $data, $matches, PREG_SET_ORDER);
foreach ($matches as [, $from, $to, $category]) {
	if ($category !== 'Mn') {
		$ranges[] = [hexdec($from), hexdec($to ?: $from)];
	}
}

sort($ranges);
$merged = [];
foreach ($ranges as [$from, $to]) {
	$last = count($merged) - 1;
	if ($last >= 0 && $from <= $merged[$last][1] + 1) {
		$merged[$last][1] = max($merged[$last][1], $to);
	} else {
		$merged[] = [$from, $to];
	}
}

$lines = [''];
foreach ($merged as [$from, $to]) {
	$item = sprintf('\x{%X}', $from) . ($from === $to ? '' : sprintf('-\x{%X}', $to));
	if (strlen(end($lines) . $item) > 100) {
		$lines[] = '';
	}

	$lines[count($lines) - 1] .= $item;
}

$file = __DIR__ . '/../src/CommandLine/Ansi.php';
$source = (string) file_get_contents($file);
$eol = str_contains($source, "\r\n") ? "\r\n" : "\n";
$code = "\t/** the characters a terminal gives two columns, from Unicode $version by tools/generate-width-table.php */$eol"
	. "\tprivate const WideCharacters = '"
	. implode("'$eol\t\t. '", $lines)
	. "';$eol";

$new = preg_replace_callback('~\t/\*\* the characters a terminal gives two columns.*?\';\r?\n~s', fn() => $code, $source, 1, $count);
$count === 1 || fail("Ansi::WideCharacters not found in $file.");
file_put_contents($file, $new) === false && fail("Cannot write $file.");
echo count($merged), " ranges written.\n";


function fail(string $message): never
{
	fwrite(STDERR, "$message\n");
	exit(1);
}
