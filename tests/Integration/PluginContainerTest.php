<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Tests\Integration;

use HachmannConsulting\CdnFetcher\Command\SyncCdnImagesCommand;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\HttpKernel\KernelInterface;

class PluginContainerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCommandIsRegistered(): void
    {
        $kernel = static::getKernel();
        static::assertInstanceOf(KernelInterface::class, $kernel);

        $command = (new Application($kernel))->find('app:sync:cdn-images');
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }

        static::assertInstanceOf(SyncCdnImagesCommand::class, $command);
    }
}
