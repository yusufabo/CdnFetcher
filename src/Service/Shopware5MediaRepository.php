<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Reads media paths from the old Shopware 5 database (table s_media).
 * The connection is created lazily from the plugin configuration.
 */
class Shopware5MediaRepository
{
    private ?Connection $connection = null;

    public function __construct(private readonly SystemConfigService $systemConfig)
    {
    }

    public function findPathByName(string $imageName): ?string
    {
        $path = $this->getConnection()->fetchOne(
            'SELECT path FROM s_media WHERE path LIKE :imageName ORDER BY id LIMIT 1',
            ['imageName' => '%' . addcslashes($imageName, '%_\\') . '%'],
        );

        return \is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * @return iterable<string>
     */
    public function iterateAllPaths(): iterable
    {
        foreach ($this->getConnection()->iterateColumn('SELECT path FROM s_media ORDER BY id') as $path) {
            if (\is_string($path) && $path !== '') {
                yield $path;
            }
        }
    }

    private function getConnection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $user = $this->systemConfig->getString('CdnFetcher.config.dbUser');
        if ($user === '') {
            throw new \RuntimeException('CdnFetcher: the Shopware 5 database is not configured (plugin settings).');
        }

        return $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->systemConfig->getString('CdnFetcher.config.dbHost'),
            'port' => $this->systemConfig->getInt('CdnFetcher.config.dbPort') ?: 3306,
            'dbname' => $this->systemConfig->getString('CdnFetcher.config.dbName'),
            'user' => $user,
            'password' => $this->systemConfig->getString('CdnFetcher.config.dbPassword'),
            'charset' => 'utf8mb4',
        ]);
    }
}
