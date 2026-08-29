<?php declare(strict_types=1);

// A program defined in code, whose help the fluent snapshots record at three widths and in color:
// kinds of items alternate, a syntax does not fit the column, defaults, Unicode and nested commands.

use Nette\CommandLine\Command;


enum ImageFormat: string
{
	case Png = 'png';
	case Jpeg = 'jpeg';
}


function buildFluent(): Command
{
	$cli = new Command('imagic', 'Converts and resizes images.');
	$cli->addFlag('--help', 'Print this help', alias: '-h', standalone: true);
	$cli->addOption('--config', 'Configuration file', alias: '-c');

	$convert = $cli->addCommand('convert', 'Converts images from one format to another');
	$convert->addFlag('--overwrite', 'Replace the files that already exist');
	$convert->addArgument('source', 'Obrázek ke konverzi, třeba fotka z dovolené');
	$convert->addOption('--format', 'Format of the result', enum: ImageFormat::class, default: ImageFormat::Png);
	$convert->addArgument('target', 'Where to write the result', optional: true);
	$convert->addOption('--quality', 'Kvalita výsledku', default: 90);
	$convert->addOption('--a-very-long-option-name-indeed', 'An option whose syntax does not fit the column', valueOptional: true);

	$remote = $cli->addCommand('remote', 'Manages remote storages');
	$remote->addCommand('add', 'Adds a storage')
		->addArgument('name', 'Name of the storage');

	return $cli;
}


/**
 * The helps of the program, of a command and of a nested command, one after another.
 */
function renderFluent(int $width, bool $colors = false): string
{
	$cli = buildFluent();
	return implode("\n----\n\n", array_map(
		fn(Command $command) => renderHelp($command, $width, $colors),
		[$cli, $cli->getCommand('convert'), $cli->getCommand('remote')->getCommand('add')],
	));
}
