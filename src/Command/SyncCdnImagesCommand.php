<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Command;

use HachmannConsulting\CdnFetcher\Service\CdnImageSyncService;
use HachmannConsulting\CdnFetcher\Service\Shopware5MediaRepository;
use HachmannConsulting\CdnFetcher\Service\SyncStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:cdn-images',
    description: 'Downloads Shopware 5 media files that are missing locally from the CDN',
)]
class SyncCdnImagesCommand extends Command
{
    public function __construct(
        private readonly Shopware5MediaRepository $mediaRepository,
        private readonly CdnImageSyncService $syncService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('imageName', InputArgument::OPTIONAL, 'Image name (or part of the path) to search for in s_media')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Sync every file from s_media');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $imageName = $input->getArgument('imageName');
        $all = (bool) $input->getOption('all');

        if ($all === (\is_string($imageName) && $imageName !== '')) {
            $io->error('Pass either an image name or --all.');

            return Command::INVALID;
        }

        if (!$all) {
            \assert(\is_string($imageName));
            $path = $this->mediaRepository->findPathByName($imageName);
            if ($path === null) {
                $io->error(\sprintf('Image "%s" not found in s_media.', $imageName));

                return Command::FAILURE;
            }

            return $this->syncPath($path, $io) === SyncStatus::DownloadFailed ? Command::FAILURE : Command::SUCCESS;
        }

        $counts = array_fill_keys(array_map(static fn (SyncStatus $s) => $s->value, SyncStatus::cases()), 0);
        foreach ($this->mediaRepository->iterateAllPaths() as $path) {
            ++$counts[$this->syncPath($path, $io)->value];
        }

        $io->table(['Status', 'Files'], array_map(null, array_keys($counts), array_values($counts)));

        return $counts[SyncStatus::DownloadFailed->value] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncPath(string $path, SymfonyStyle $io): SyncStatus
    {
        try {
            $status = $this->syncService->sync($path);
        } catch (\InvalidArgumentException $e) {
            $io->writeln('<comment>Skipped:</comment> ' . $e->getMessage());

            return SyncStatus::DownloadFailed;
        }

        $message = match ($status) {
            SyncStatus::Exists => '<info>Already exists:</info> ',
            SyncStatus::Downloaded => '<info>Downloaded from CDN:</info> ',
            SyncStatus::CdnNotFound => '<comment>Not found on CDN:</comment> ',
            SyncStatus::DownloadFailed => '<error>Download failed:</error> ',
        };
        $io->writeln($message . $path);

        if ($status !== SyncStatus::Exists) {
            $this->logger->info('CdnFetcher: {status} {path}', ['status' => $status->value, 'path' => $path]);
        }

        return $status;
    }
}
