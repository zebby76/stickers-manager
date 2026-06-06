<?php

namespace App\Command;

use App\Service\AlbumImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:album:import',
    description: 'Import an album from a JSON (full album) or CSV (stickers) file.',
)]
class ImportAlbumCommand extends Command
{
    public function __construct(private readonly AlbumImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the .json or .csv file')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Album name (required for CSV imports)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        if (!is_file($file) || !is_readable($file)) {
            $io->error(sprintf('File not found or unreadable: %s', $file));

            return Command::FAILURE;
        }

        $content = (string) file_get_contents($file);
        $isJson = str_ends_with(strtolower($file), '.json') || str_starts_with(ltrim($content), '{');

        try {
            $result = $isJson
                ? $this->importer->importJson($content)
                : $this->importer->importCsv($content, (string) ($input->getOption('name') ?? ''));
        } catch (\Throwable $e) {
            $io->error('Import failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Imported album "%s" with %d sticker(s). Slug: %s',
            $result['album']->getName(),
            $result['imported'],
            $result['album']->getSlug(),
        ));

        return Command::SUCCESS;
    }
}
