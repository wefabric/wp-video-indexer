<?php

namespace Wefabric\WPVideoIndexer\Tests\Hooks;

use Brain\Monkey\Functions;
use Wefabric\WPVideoIndexer\Hooks\VideoIndexerHook;
use Wefabric\WPVideoIndexer\Services\VideoSchemaCacheService;
use Wefabric\WPVideoIndexer\Tests\TestCase;

class VideoIndexerHookTest extends TestCase
{
    private VideoIndexerHook $hook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hook = new VideoIndexerHook();

        Functions\when('add_action')->justReturn(null);
    }

    public function test_cache_video_schemas_delegates_to_cache_service(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('get_post_field')->justReturn($url);
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'title'         => 'Never Gonna Give You Up',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ]));
        Functions\expect('update_post_meta')
            ->once()
            ->with(1, VideoSchemaCacheService::META_KEY, \Mockery::type('array'))
            ->andReturnUsing(function ($post_id, $key, $schemas) {
                $this->assertSame(VideoSchemaCacheService::META_KEY, $key);
                return true;
            });

        $this->hook->cacheVideoSchemas(1);
    }

    public function test_output_uses_meta_key_constant(): void
    {
        Functions\when('is_singular')->justReturn(true);
        Functions\expect('get_post_meta')
            ->once()
            ->with(\Mockery::any(), VideoSchemaCacheService::META_KEY, true)
            ->andReturnUsing(function ($post_id, $key) {
                $this->assertSame(VideoSchemaCacheService::META_KEY, $key);
                return [];
            });

        $GLOBALS['post'] = (object) ['ID' => 1];
        $this->hook->outputCachedVideoSchemas();
        unset($GLOBALS['post']);
    }
}
