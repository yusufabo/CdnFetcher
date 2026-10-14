<?php declare(strict_types=1);

use HachmannConsulting\CdnFetcher\Command\SyncCdnImagesCommand;
use HachmannConsulting\CdnFetcher\Service\CdnImageSyncService;
use HachmannConsulting\CdnFetcher\Service\MediaPathEncoder;
use HachmannConsulting\CdnFetcher\Service\Shopware5MediaRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MediaPathEncoder::class);

    $services->set(Shopware5MediaRepository::class)
        ->args([service(SystemConfigService::class)]);

    $services->set(CdnImageSyncService::class)
        ->args([
            service('http_client'),
            service(MediaPathEncoder::class),
            service(SystemConfigService::class),
        ]);

    $services->set(SyncCdnImagesCommand::class)
        ->args([
            service(Shopware5MediaRepository::class),
            service(CdnImageSyncService::class),
            service('logger'),
        ])
        ->tag('console.command');
};
