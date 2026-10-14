<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Tests\Unit\Service;

use HachmannConsulting\CdnFetcher\Service\CdnImageSyncService;
use HachmannConsulting\CdnFetcher\Service\MediaPathEncoder;
use HachmannConsulting\CdnFetcher\Service\SyncStatus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class CdnImageSyncServiceTest extends TestCase
{
    private const PATH = 'media/image/foo.jpg';

    private string $mediaRoot;

    private string $encodedPath;

    protected function setUp(): void
    {
        $this->mediaRoot = sys_get_temp_dir() . '/cdn-fetcher-test-' . bin2hex(random_bytes(4));
        mkdir($this->mediaRoot);
        $this->encodedPath = (new MediaPathEncoder())->encode(self::PATH);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->mediaRoot);
    }

    public function testExistingFileIsNotDownloaded(): void
    {
        mkdir(\dirname($this->mediaRoot . '/' . $this->encodedPath), 0777, true);
        file_put_contents($this->mediaRoot . '/' . $this->encodedPath, 'local');

        $client = new MockHttpClient(static fn () => throw new \LogicException('CDN must not be called'));

        static::assertSame(SyncStatus::Exists, $this->createService($client)->sync(self::PATH));
    }

    public function testMissingFileIsDownloadedFromCdn(): void
    {
        $requestedUrl = null;
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse('image-bytes');
        });

        static::assertSame(SyncStatus::Downloaded, $this->createService($client)->sync(self::PATH));
        static::assertSame('https://cdn.example/' . $this->encodedPath, $requestedUrl);
        static::assertSame('image-bytes', file_get_contents($this->mediaRoot . '/' . $this->encodedPath));
        static::assertFileDoesNotExist($this->mediaRoot . '/' . $this->encodedPath . '.part');
    }

    public function testCdn404ReturnsNotFound(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 404]));

        static::assertSame(SyncStatus::CdnNotFound, $this->createService($client)->sync(self::PATH));
        static::assertFileDoesNotExist($this->mediaRoot . '/' . $this->encodedPath);
    }

    public function testServerErrorReturnsDownloadFailed(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        static::assertSame(SyncStatus::DownloadFailed, $this->createService($client)->sync(self::PATH));
    }

    public function testNetworkErrorReturnsDownloadFailedAndLeavesNoFile(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'connection reset']));

        static::assertSame(SyncStatus::DownloadFailed, $this->createService($client)->sync(self::PATH));
        static::assertFileDoesNotExist($this->mediaRoot . '/' . $this->encodedPath);
        static::assertFileDoesNotExist($this->mediaRoot . '/' . $this->encodedPath . '.part');
    }

    private function createService(MockHttpClient $client): CdnImageSyncService
    {
        $config = new StaticSystemConfigService([
            'CdnFetcher.config.cdnBaseUrl' => 'https://cdn.example/',
            'CdnFetcher.config.localMediaRoot' => $this->mediaRoot . '/',
        ]);

        return new CdnImageSyncService($client, new MediaPathEncoder(), $config);
    }
}
