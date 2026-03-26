<?php

namespace Wefabric\WPVideoIndexer\Hooks;

use Themosis\Hook\Hookable;
use Wefabric\WPVideoIndexer\Services\VideoSchemaCacheService;
use Wefabric\WPVideoIndexer\Services\VideoSchemaOutputService;
use Wefabric\WPVideoIndexer\Services\VideoSchemaServiceInterface;
use Wefabric\WPVideoIndexer\Services\VimeoSchemaService;
use Wefabric\WPVideoIndexer\Services\YouTubeSchemaService;

class VideoIndexerHook extends Hookable
{
    /** @var class-string<VideoSchemaServiceInterface>[] */
    protected array $services = [
        YouTubeSchemaService::class,
        VimeoSchemaService::class,
    ];

    public function register(): void
    {
        add_action('save_post', [$this, 'cacheVideoSchemas']);
        add_action('wp_head', [$this, 'outputCachedVideoSchemas']);
    }

    public function cacheVideoSchemas(int $post_id): void
    {
        (new VideoSchemaCacheService($this->services))->cache($post_id);
    }

    public function outputCachedVideoSchemas(): void
    {
        (new VideoSchemaOutputService())->output();
    }
}
