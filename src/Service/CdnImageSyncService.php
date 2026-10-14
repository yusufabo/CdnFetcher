<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Makes sure a Shopware 5 media file exists locally, downloading it from the CDN if it is missing.
 */
class CdnImageSyncService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MediaPathEncoder $pathEncoder,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public function sync(string $virtualPath): SyncStatus
    {
        $relativePath = $this->pathEncoder->encode($virtualPath);
        $localPath = $this->getLocalMediaRoot() . '/' . $relativePath;

        if (is_file($localPath)) {
            return SyncStatus::Exists;
        }

        $url = $this->getCdnBaseUrl() . '/' . implode('/', array_map('rawurlencode', explode('/', $relativePath)));

        try {
            $response = $this->httpClient->request('GET', $url);
            $statusCode = $response->getStatusCode();
            if ($statusCode === 404 || $statusCode === 403) {
                return SyncStatus::CdnNotFound;
            }
            if ($statusCode !== 200) {
                return SyncStatus::DownloadFailed;
            }

            return $this->writeFile($localPath, $this->httpClient->stream($response))
                ? SyncStatus::Downloaded
                : SyncStatus::DownloadFailed;
        } catch (ExceptionInterface) {
            return SyncStatus::DownloadFailed;
        }
    }

    /**
     * Writes into a temp file first, so an aborted download never leaves a broken image behind.
     *
     * @param iterable<\Symfony\Contracts\HttpClient\ChunkInterface> $chunks
     */
    private function writeFile(string $localPath, iterable $chunks): bool
    {
        $dir = \dirname($localPath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $tmpPath = $localPath . '.part';
        $handle = @fopen($tmpPath, 'w');
        if ($handle === false) {
            return false;
        }

        try {
            foreach ($chunks as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } catch (ExceptionInterface $e) {
            fclose($handle);
            @unlink($tmpPath);

            throw $e;
        }

        fclose($handle);

        if (!@rename($tmpPath, $localPath)) {
            @unlink($tmpPath);

            return false;
        }

        return true;
    }

    private function getLocalMediaRoot(): string
    {
        $root = rtrim($this->systemConfig->getString('CdnFetcher.config.localMediaRoot'), '/');
        if ($root === '') {
            throw new \RuntimeException('CdnFetcher: "localMediaRoot" is not configured.');
        }

        return $root;
    }

    private function getCdnBaseUrl(): string
    {
        $url = rtrim($this->systemConfig->getString('CdnFetcher.config.cdnBaseUrl'), '/');
        if ($url === '') {
            throw new \RuntimeException('CdnFetcher: "cdnBaseUrl" is not configured.');
        }

        return $url;
    }
}
