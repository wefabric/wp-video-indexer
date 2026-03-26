<?php

namespace Wefabric\WPVideoIndexer\Tests\Services;

use Brain\Monkey\Functions;
use Wefabric\WPVideoIndexer\Services\VideoSchemaCacheService;
use Wefabric\WPVideoIndexer\Services\VimeoSchemaService;
use Wefabric\WPVideoIndexer\Services\YouTubeSchemaService;
use Wefabric\WPVideoIndexer\Tests\TestCase;

class VideoSchemaCacheServiceTest extends TestCase
{
    private VideoSchemaCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VideoSchemaCacheService([
            YouTubeSchemaService::class,
            VimeoSchemaService::class,
        ]);

        Functions\when('wp_is_post_revision')->justReturn(false);
    }

    public function test_meta_key_constant(): void
    {
        $this->assertSame('_cached_video_schemas', VideoSchemaCacheService::META_KEY);
    }

    public function test_reuses_cached_schema_without_fetching(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $cached = [
            $url => [
                '@context'     => 'https://schema.org',
                '@type'        => 'VideoObject',
                'name'         => 'Cached Title',
                'description'  => 'Cached Title',
                'thumbnailUrl' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
                'embedUrl'     => $url,
                'contentUrl'   => $url,
                'uploadDate'   => '2024-01-01T00:00:00+00:00',
            ],
        ];

        Functions\when('get_post_field')->justReturn($url);
        Functions\when('get_post_meta')->justReturn($cached);
        Functions\expect('wp_remote_get')->never();
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function ($post_id, $key, $schemas) use ($cached) {
                $this->assertSame($cached, $schemas);
                return true;
            });

        $this->service->cache(1);
    }

    public function test_fetches_schema_for_new_url_not_in_cache(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

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
            ->andReturnUsing(function ($post_id, $key, $schemas) use ($url) {
                $this->assertArrayHasKey($url, $schemas);
                $this->assertSame('Never Gonna Give You Up', $schemas[$url]['name']);
                $this->assertSame($url, $schemas[$url]['contentUrl']);
                return true;
            });

        $this->service->cache(1);
    }

    public function test_skips_post_revision(): void
    {
        Functions\when('wp_is_post_revision')->justReturn(true);
        Functions\when('get_post_field')->justReturn('');

        $this->service->cache(1);

        // If we reach this point without update_post_meta being called, the revision was skipped.
        // Brain Monkey will fail the test if update_post_meta is called unexpectedly.
        $this->addToAssertionCount(1);
    }

    public function test_skips_post_without_content(): void
    {
        Functions\when('get_post_field')->justReturn('');

        $this->service->cache(1);

        $this->addToAssertionCount(1);
    }

    public function test_skips_post_with_no_video_urls(): void
    {
        Functions\when('get_post_field')->justReturn('No videos here, just plain text.');

        $this->service->cache(1);

        $this->addToAssertionCount(1);
    }

    public function test_finds_video_url_in_acf_fields(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        Functions\when('get_post_field')->justReturn('');
        Functions\when('get_fields')->justReturn([
            'field_60d5e7f3c2966' => '',
            'field_60afe3979d1ae' => [
                'row-0' => [
                    'acf_fc_layout'       => 'textblock',
                    'field_60afe836076a6' => 'Some text with a video ' . $url . ' check it out',
                ],
            ],
        ]);
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'title'         => 'Never Gonna Give You Up',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ]));
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function ($post_id, $key, $schemas) use ($url) {
                $this->assertArrayHasKey($url, $schemas);
                $this->assertSame('Never Gonna Give You Up', $schemas[$url]['name']);
                return true;
            });

        $this->service->cache(1);
    }

    public function test_skips_acf_lookup_when_get_fields_returns_empty(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        Functions\when('get_post_field')->justReturn($url);
        Functions\when('get_fields')->justReturn(false);
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'title' => 'Never Gonna Give You Up',
        ]));
        Functions\expect('update_post_meta')
            ->once()
            ->andReturnUsing(function ($post_id, $key, $schemas) use ($url) {
                $this->assertArrayHasKey($url, $schemas);
                return true;
            });

        $this->service->cache(1);
    }
}
