# wefabric/wp-video-indexer

Indexes YouTube and Vimeo videos found in WordPress post content and ACF fields as `VideoObject` schema markup (JSON-LD).

## How it works

1. On `save_post`, the package scans `post_content` and all ACF fields for YouTube and Vimeo URLs (including iframe embed URLs).
2. For each unique video found, it fetches metadata via the platform's oEmbed API and builds a `VideoObject` schema.
3. The schemas are cached in post meta, keyed by URL. On subsequent saves, already-cached videos are reused without a new HTTP request.
4. On `wp_head` (singular pages only), the cached schemas are output as `<script type="application/ld+json">` tags.

## Installation

The package is available on [Packagist](https://packagist.org/packages/wefabric/wp-video-indexer).

```bash
composer require wefabric/wp-video-indexer:^1.0
php artisan package:discover
```

## Requirements

- PHP >= 7.4
- WordPress
- Themosis framework (for hook registration and service container)
- `wefabric/wp-support` (for `WPSupport::addHooks`)

## Supported URL formats

### YouTube
| Format | Example |
|--------|---------|
| Watch URL | `https://youtube.com/watch?v=ID` |
| Short URL | `https://youtu.be/ID` |
| Embed iframe | `https://youtube.com/embed/ID` |
| Legacy embed | `https://youtube.com/v/ID` |

### Vimeo
| Format | Example |
|--------|---------|
| Standard URL | `https://vimeo.com/ID` |
| URL with query params | `https://vimeo.com/ID?fl=pl&fe=vl` |
| Embed iframe | `https://player.vimeo.com/video/ID` |
| Channel URL | `https://vimeo.com/channels/name/ID` |

## Structure

```
src/
├── Hooks/
│   └── VideoIndexerHook.php            Registers save_post + wp_head, delegates to services
├── Providers/
│   └── ServiceProvider.php             Laravel service provider (auto-discovered)
└── Services/
    ├── VideoSchemaServiceInterface.php  Contract for all platform services
    ├── VideoSchemaCacheService.php      Scans content, fetches/reuses schemas, saves to post meta
    ├── VideoSchemaOutputService.php     Reads post meta and outputs JSON-LD script tags
    ├── YouTubeSchemaService.php         YouTube oEmbed fetching and schema generation
    └── VimeoSchemaService.php           Vimeo oEmbed fetching and schema generation
```

## Adding a new platform

Each platform is a service class implementing `VideoSchemaServiceInterface`:

```php
interface VideoSchemaServiceInterface
{
    public function matches(string $url): bool;
    public function extractId(string $url): ?string;
    public function fetchSchema(string $url): array;
}
```

1. Create a new service class implementing the interface:

```php
class DailymotionSchemaService implements VideoSchemaServiceInterface
{
    public function matches(string $url): bool
    {
        return strpos($url, 'dailymotion.com') !== false;
    }

    public function extractId(string $url): ?string
    {
        // extract ID from URL
    }

    public function fetchSchema(string $url): array
    {
        // fetch oEmbed data and return a VideoObject schema array
    }
}
```

2. Register it in `VideoIndexerHook::$services`:

```php
protected array $services = [
    YouTubeSchemaService::class,
    VimeoSchemaService::class,
    DailymotionSchemaService::class, // add here
];
```

No other changes are needed.

## Testing

```bash
composer install
./vendor/bin/phpunit
```

Tests cover `matches()`, `extractId()`, and `fetchSchema()` (including fallback behaviour) for each platform service. WordPress functions are mocked using [Brain Monkey](https://github.com/Brain-WP/BrainMonkey).

## Post meta

| Key                      | Type  | Description                                        |
|--------------------------|---------|----------------------------------------------------|
| `_cached_video_schemas`  | array | Schemas keyed by URL: `[ $url => $schema, ... ]`   |

Schemas are re-cached each time a post is saved. Already-cached URLs are reused without a new API call. To force a full refresh, delete the `_cached_video_schemas` post meta and re-save the post.
