# CDN Fetcher for Shopware 6.7 (`CdnFetcher` plugin)

Downloads media files from an old **Shopware 5** shop that are missing on the local disk. The paths come from the Shopware 5 table `s_media`, and the files are fetched from the CDN and stored in the same hashed folder layout that Shopware 5 uses.

| | |
|---|---|
| Shopware | 6.7 |
| PHP | 8.4+ |
| Composer name | `hachmann-consulting/cdn-fetcher` |
| Plugin class | `HachmannConsulting\CdnFetcher\CdnFetcher` |
| Console command | `app:sync:cdn-images` |

## Contents

1. [How it works](#how-it-works)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)
5. [Tests](#tests)
6. [Structure](#structure)

---

## How it works

For every path from `s_media` (for example `media/image/shirt.jpg`):

1. **Work out the real file location.** Shopware 5 doesn't store files under their virtual path. It takes the md5 hash of the virtual path and uses its first three character pairs as folders:

   ```
   media/image/shirt.jpg  ->  media/image/55/38/fc/shirt.jpg
   ```

   Like Shopware 5, a folder named `ad` is renamed to `g0`, because ad blockers block URLs containing `/ad/`.
2. **Check the local disk.** If the file already exists under the configured media root, nothing happens.
3. **Download from the CDN.** Otherwise the file is requested from `<CDN base URL>/<real path>` and saved locally. Missing folders are created.
   - The download goes to a `.part` file first and is renamed when it is complete, so a failed download never leaves a broken image behind.

Each file ends with one of these statuses:

| Status | Meaning |
|---|---|
| `exist` | The file is already on the local disk. |
| `downloaded_from_cdn` | The file was missing and has been downloaded. |
| `cdn_not_found` | The CDN answered with 404 or 403. |
| `download_failed` | Another HTTP error, a network error, or the file could not be written. |

Downloads and errors are also written to Shopware's log (`var/log/`).

## Installation

```bash
bin/console plugin:refresh
bin/console plugin:install --activate CdnFetcher
bin/console cache:clear
```

## Configuration

*Administration → Extensions → My extensions → CDN Fetcher → Configure*

| Card | Setting | Default | Description |
|---|---|---|---|
| Shopware 5 database | Host | `127.0.0.1` | Host of the Shopware 5 database. |
| | Port | `3306` | |
| | Database name | `shopware5` | |
| | User | *(empty)* | **Required.** The command stops with an error while this is empty. |
| | Password | *(empty)* | Stored as a password field. |
| Media | CDN base URL | `https://mppull2.b-cdn.net` | The `media/...` path is appended to it. |
| | Local Shopware 5 root directory | `/opt/bitnami/apache/htdocs/web` | The folder that **contains** `media/`. |

The settings can also be set from the command line:

```bash
bin/console system:config:set CdnFetcher.config.dbUser '<db-user>'
bin/console system:config:set CdnFetcher.config.dbPassword '<db-password>'
```

The database user only needs `SELECT` on `s_media`. Credentials are never stored in the code.

## Usage

Sync a single image. The name is searched in `s_media.path` (`LIKE %name%`), and the first match is used:

```bash
bin/console app:sync:cdn-images shirt.jpg
```

```
Downloaded from CDN: media/image/shirt.jpg
```

Sync every file in `s_media`:

```bash
bin/console app:sync:cdn-images --all
```

```
Already exists: media/image/shirt.jpg
Downloaded from CDN: media/image/a36.jpg
Not found on CDN: media/image/gone.png
 --------------------- -------
  Status                Files
 --------------------- -------
  exist                 1
  downloaded_from_cdn   1
  cdn_not_found         1
  download_failed       0
 --------------------- -------
```

Running it again is safe: files that already exist are skipped, so an interrupted run can simply be started again.

| Exit code | When |
|---|---|
| `0` | Everything was found locally, downloaded, or is not on the CDN. |
| `1` | The image name was not found in `s_media`, or at least one download failed. |
| `2` | Neither an image name nor `--all` was passed, or both were. |

## Tests

The tests run from the project root, together with the other plugins:

```bash
docker compose exec web vendor/bin/phpunit --testsuite CdnFetcher --testdox
```

On the host, pass the database URL, because Symfony ignores `.env.local` in the test environment:

```bash
DATABASE_URL=mysql://<user>:<password>@127.0.0.1:33306/shopware vendor/bin/phpunit --testsuite CdnFetcher --testdox
```

| Test | Type | What it checks |
|---|---|---|
| `Unit/Service/MediaPathEncoderTest` | Unit | The md5 folder layout, normalising of URLs and leading slashes, other media types, the `ad` → `g0` rewrite, and invalid paths. |
| `Unit/Service/CdnImageSyncServiceTest` | Unit | Existing files aren't downloaded, missing files are downloaded to the right place, 404, 500 and network errors, and no broken or `.part` files are left behind. Uses `MockHttpClient` and a temp folder. |
| `Unit/Command/SyncCdnImagesCommandTest` | Unit | Single image, unknown image, `--all` with summary and exit code, and invalid arguments. |
| `Integration/PluginContainerTest` | Integration | The plugin boots in Shopware and the command is registered. |

PHPStan (level max) and PHP-CS-Fixer also cover this plugin (`phpstan.dist.neon`, `.php-cs-fixer.dist.php`).

## Structure

```
custom/plugins/CdnFetcher/
├── src/
│   ├── CdnFetcher.php
│   ├── Command/SyncCdnImagesCommand.php       console command app:sync:cdn-images
│   ├── Service/
│   │   ├── MediaPathEncoder.php                Shopware 5 md5 path strategy
│   │   ├── CdnImageSyncService.php             local check + CDN download
│   │   ├── Shopware5MediaRepository.php        reads s_media (lazy connection from plugin config)
│   │   └── SyncStatus.php                      result enum
│   └── Resources/config/
│       ├── config.xml                          plugin settings
│       └── services.php
└── tests/
    ├── Unit/
    └── Integration/
```
