<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Tests\Unit\Service;

use HachmannConsulting\CdnFetcher\Service\MediaPathEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaPathEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathProvider(): iterable
    {
        $hash = md5('media/image/foo.jpg');
        $expected = 'media/image/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . substr($hash, 4, 2) . '/foo.jpg';

        yield 'plain path' => ['media/image/foo.jpg', $expected];
        yield 'leading slash' => ['/media/image/foo.jpg', $expected];
        yield 'full url' => ['https://shop.example/web/media/image/foo.jpg', $expected];
    }

    #[DataProvider('pathProvider')]
    public function testEncodeUsesMd5OfVirtualPath(string $input, string $expected): void
    {
        static::assertSame($expected, (new MediaPathEncoder())->encode($input));
    }

    public function testEncodeKeepsMediaType(): void
    {
        static::assertStringStartsWith('media/pdf/', (new MediaPathEncoder())->encode('media/pdf/manual.pdf'));
    }

    public function testAdFolderIsRewrittenLikeShopware5(): void
    {
        // md5('media/image/a36.jpg') starts with "ad" -> SW5 stores it in "g0" instead
        $path = 'media/image/a36.jpg';
        static::assertStringStartsWith('ad', md5($path));

        static::assertStringStartsWith('media/image/g0/', (new MediaPathEncoder())->encode($path));
    }

    public function testRejectsPathWithoutMediaFolder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MediaPathEncoder())->encode('foo.jpg');
    }
}
