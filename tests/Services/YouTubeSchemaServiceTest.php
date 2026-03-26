<?php

namespace Wefabric\WPVideoIndexer\Tests\Services;

use Brain\Monkey\Functions;
use Wefabric\WPVideoIndexer\Services\YouTubeSchemaService;
use Wefabric\WPVideoIndexer\Tests\TestCase;

class YouTubeSchemaServiceTest extends TestCase
{
    private YouTubeSchemaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YouTubeSchemaService();
    }

    // --- matches() ---

    public function test_matches_youtube_com_url(): void
    {
        $this->assertTrue($this->service->matches('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_matches_youtu_be_url(): void
    {
        $this->assertTrue($this->service->matches('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function test_does_not_match_vimeo_url(): void
    {
        $this->assertFalse($this->service->matches('https://vimeo.com/123456789'));
    }

    public function test_does_not_match_unrelated_url(): void
    {
        $this->assertFalse($this->service->matches('https://example.com/video'));
    }

    // --- extractId() ---

    public function test_extracts_id_from_watch_url(): void
    {
        $this->assertSame(
            'dQw4w9WgXcQ',
            $this->service->extractId('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
    }

    public function test_extracts_id_from_url_with_extra_params(): void
    {
        $this->assertSame(
            'dQw4w9WgXcQ',
            $this->service->extractId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s')
        );
    }

    public function test_extracts_id_from_embed_url(): void
    {
        $this->assertSame(
            'l-_7ckZ8i3w',
            $this->service->extractId('https://www.youtube.com/embed/l-_7ckZ8i3w')
        );
    }

    public function test_extracts_id_from_youtu_be_url(): void
    {
        $this->assertSame(
            'dQw4w9WgXcQ',
            $this->service->extractId('https://youtu.be/dQw4w9WgXcQ')
        );
    }

    public function test_returns_null_for_non_video_url(): void
    {
        $this->assertNull($this->service->extractId('https://www.youtube.com/channel/UCxxx'));
    }

    // --- fetchSchema() ---

    public function test_fetch_schema_returns_video_object_on_success(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'title'         => 'Never Gonna Give You Up',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
        ]));

        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $schema = $this->service->fetchSchema($url);

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('VideoObject', $schema['@type']);
        $this->assertSame('Never Gonna Give You Up', $schema['name']);
        $this->assertSame('Never Gonna Give You Up', $schema['description']);
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $schema['thumbnailUrl']);
        $this->assertSame($url, $schema['embedUrl']);
        $this->assertSame($url, $schema['contentUrl']);
    }

    public function test_fetch_schema_returns_fallback_on_wp_error(): void
    {
        Functions\when('wp_remote_get')->justReturn(null);
        Functions\when('is_wp_error')->justReturn(true);

        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $schema = $this->service->fetchSchema($url);

        $this->assertSame('VideoObject', $schema['@type']);
        $this->assertSame('YouTube Video', $schema['name']);
        $this->assertSame($url, $schema['embedUrl']);
    }

    public function test_fetch_schema_returns_fallback_when_response_has_no_title(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([]));

        $schema = $this->service->fetchSchema('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->assertSame('YouTube Video', $schema['name']);
    }
}
