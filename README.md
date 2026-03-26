# wefabric/wp-video-indexer

Indexes YouTube and Vimeo videos found in WordPress post content as `VideoObject` schema markup (JSON-LD).

## How it works

1. On `save_post`, the package scans post content for YouTube and Vimeo URLs.
2. For each unique video found, it fetches metadata via the platform's oEmbed API and builds a `VideoObject` schema.
3. The schemas are cached in post meta (`_cached_video_schemas`).
4. On `wp_head` (singular pages only), the cached schemas are output as `<script type="application/ld+json">` tags.

## Installation

Add the path repository and require the package in your root `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "./packages/wefabric/wp-video-indexer"
    }
],
"require": {
    "wefabric/wp-video-indexer": "@dev"
}
```

Then run:

```bash
composer require wefabric/wp-video-indexer:@dev
php artisan package:discover
```

When the package is published to a VCS (GitHub/Bitbucket), replace the path repository with a VCS entry:

```json
{
    "type": "vcs",
    "url": "git@github.com:wefabric/wp-video-indexer.git"
}
```

And update the version constraint accordingly.

## Requirements

- PHP >= 8.0
- WordPress
- Themosis framework (for hook registration and service container)
- `wefabric/wp-support` (for `WPSupport::addHooks`)

## Structure

```
src/
├── Hooks/
│   └── VideoIndexerHook.php           WordPress save_post + wp_head hooks
├── Providers/
│   └── ServiceProvider.php            Laravel service provider (auto-discovered)
└── Services/
    ├── VideoSchemaServiceInterface.php Contract for all platform services
    ├── YouTubeSchemaService.php        YouTube oEmbed fetching and schema generation
    └── VimeoSchemaService.php          Vimeo oEmbed fetching and schema generation
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
        return str_contains($url, 'dailymotion.com');
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

## Post meta

| Key                      | Type  | Description                               |
|--------------------------|-------|-------------------------------------------|
| `_cached_video_schemas`  | array | Cached `VideoObject` schemas for the post |

Schemas are re-cached each time a post is saved. To force a refresh, simply re-save the post.
