<?php declare(strict_types=1);

namespace HachmannConsulting\CdnFetcher\Service;

enum SyncStatus: string
{
    case Exists = 'exist';
    case Downloaded = 'downloaded_from_cdn';
    case CdnNotFound = 'cdn_not_found';
    case DownloadFailed = 'download_failed';
}
