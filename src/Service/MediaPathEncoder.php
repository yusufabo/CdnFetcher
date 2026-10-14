<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Service;

/**
 * Port of Shopware 5's Md5Strategy: "media/image/foo.jpg" is stored as
 * "media/image/<md5[0:2]>/<md5[2:4]>/<md5[4:6]>/foo.jpg", where md5 is taken
 * from the virtual path. Ad-blockers block "/ad/" folders, so SW5 rewrites them to "/g0/".
 */
class MediaPathEncoder
{
    private const BLACKLIST = ['/ad/' => '/g0/'];

    public function encode(string $virtualPath): string
    {
        $path = $this->normalize($virtualPath);
        $segments = explode('/', $path);

        if (\count($segments) < 3 || $segments[0] !== 'media') {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a Shopware 5 media path like "media/image/foo.jpg".', $virtualPath));
        }

        $hashFolders = implode('/', \array_slice(str_split(md5($path), 2), 0, 3));
        $encoded = $segments[0] . '/' . $segments[1] . '/' . $hashFolders . '/' . basename($path);

        return strtr($encoded, self::BLACKLIST);
    }

    /**
     * Strips anything before "media/" (e.g. a host or "web/") and leading slashes.
     */
    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $mediaPos = strpos($path, 'media/');
        if ($mediaPos !== false) {
            $path = substr($path, $mediaPos);
        }

        return ltrim($path, '/');
    }
}
