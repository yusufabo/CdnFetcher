<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Tests\Unit\Command;

use HachmannConsulting\CdnFetcher\Command\SyncCdnImagesCommand;
use HachmannConsulting\CdnFetcher\Service\CdnImageSyncService;
use HachmannConsulting\CdnFetcher\Service\Shopware5MediaRepository;
use HachmannConsulting\CdnFetcher\Service\SyncStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SyncCdnImagesCommandTest extends TestCase
{
    public function testSyncsThePathFoundInTheDatabase(): void
    {
        $repository = $this->createMock(Shopware5MediaRepository::class);
        $repository->method('findPathByName')->with('foo')->willReturn('media/image/foo.jpg');

        $syncService = $this->createMock(CdnImageSyncService::class);
        $syncService->expects(static::once())->method('sync')->with('media/image/foo.jpg')->willReturn(SyncStatus::Downloaded);

        $tester = new CommandTester(new SyncCdnImagesCommand($repository, $syncService, new NullLogger()));

        static::assertSame(Command::SUCCESS, $tester->execute(['imageName' => 'foo']));
        static::assertStringContainsString('Downloaded from CDN: media/image/foo.jpg', $tester->getDisplay());
    }

    public function testUnknownImageFails(): void
    {
        $repository = $this->createMock(Shopware5MediaRepository::class);
        $repository->method('findPathByName')->willReturn(null);

        $syncService = $this->createMock(CdnImageSyncService::class);
        $syncService->expects(static::never())->method('sync');

        $tester = new CommandTester(new SyncCdnImagesCommand($repository, $syncService, new NullLogger()));

        static::assertSame(Command::FAILURE, $tester->execute(['imageName' => 'missing']));
    }

    public function testAllSyncsEveryPathAndFailsIfOneDownloadFailed(): void
    {
        $repository = $this->createMock(Shopware5MediaRepository::class);
        $repository->method('iterateAllPaths')->willReturn(['media/image/a.jpg', 'media/image/b.jpg', 'media/image/c.jpg']);

        $syncService = $this->createMock(CdnImageSyncService::class);
        $syncService->method('sync')->willReturnOnConsecutiveCalls(SyncStatus::Exists, SyncStatus::Downloaded, SyncStatus::DownloadFailed);

        $tester = new CommandTester(new SyncCdnImagesCommand($repository, $syncService, new NullLogger()));

        static::assertSame(Command::FAILURE, $tester->execute(['--all' => true]));
        static::assertMatchesRegularExpression('/downloaded_from_cdn\s+1/', $tester->getDisplay());
    }

    public function testRequiresEitherNameOrAll(): void
    {
        $tester = new CommandTester(new SyncCdnImagesCommand(
            $this->createMock(Shopware5MediaRepository::class),
            $this->createMock(CdnImageSyncService::class),
            new NullLogger(),
        ));

        static::assertSame(Command::INVALID, $tester->execute([]));
        static::assertSame(Command::INVALID, $tester->execute(['imageName' => 'foo', '--all' => true]));
    }
}
