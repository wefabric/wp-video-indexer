<?php

namespace Wefabric\WPVideoIndexer\Tests\Services;

use Brain\Monkey\Functions;
use Wefabric\WPVideoIndexer\Services\VimeoSchemaService;
use Wefabric\WPVideoIndexer\Tests\TestCase;

class VimeoSchemaServiceTest extends TestCase
{
    private VimeoSchemaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VimeoSchemaService();
    }

    // --- matches() ---

    public function test_matches_vimeo_url(): void
    {
        $this->assertTrue($this->service->matches('https://vimeo.com/1172365854'));
    }

    public function test_matches_player_vimeo_url(): void
    {
        $this->assertTrue($this->service->matches('https://player.vimeo.com/video/1172365854'));
    }

    public function test_does_not_match_youtube_url(): void
    {
        $this->assertFalse($this->service->matches('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_does_not_match_unrelated_url(): void
    {
        $this->assertFalse($this->service->matches('https://example.com/video'));
    }

    // --- extractId() ---

    public function test_extracts_id_from_standard_url(): void
    {
        $this->assertSame('1172365854', $this->service->extractId('https://vimeo.com/1172365854'));
    }

    public function test_extracts_id_from_url_with_query_params(): void
    {
        $this->assertSame('1172365854', $this->service->extractId('https://vimeo.com/1172365854?fl=pl&fe=vl'));
    }

    public function test_extracts_id_from_player_url(): void
    {
        $this->assertSame('1172365854', $this->service->extractId('https://player.vimeo.com/video/1172365854'));
    }

    public function test_extracts_id_from_channel_url(): void
    {
        $this->assertSame('1172365854', $this->service->extractId('https://vimeo.com/channels/staffpicks/1172365854'));
    }

    public function test_returns_null_for_url_without_numeric_id(): void
    {
        $this->assertNull($this->service->extractId('https://vimeo.com/channels/staffpicks'));
    }

    // --- fetchSchema() ---

    public function test_fetch_schema_returns_video_object_on_success(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'title'         => 'My Vimeo Video',
            'description'   => 'A great video',
            'thumbnail_url' => 'https://i.vimeocdn.com/video/123456789.jpg',
            'html'          => '<iframe src="https://player.vimeo.com/video/1172365854"></iframe>',
            'upload_date'   => '2024-01-15 10:00:00',
        ]));
        Functions\when('wp_strip_all_tags')->returnArg();

        $url = 'https://vimeo.com/1172365854';
        $schema = $this->service->fetchSchema($url);

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('VideoObject', $schema['@type']);
        $this->assertSame('My Vimeo Video', $schema['name']);
        $this->assertSame('A great video', $schema['description']);
        $this->assertSame('https://i.vimeocdn.com/video/123456789.jpg', $schema['thumbnailUrl']);
        $this->assertSame('https://player.vimeo.com/video/1172365854', $schema['embedUrl']);
        $this->assertSame($url, $schema['contentUrl']);
    }

    public function test_fetch_schema_uses_title_as_description_when_description_is_empty(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([
            'title'         => 'My Vimeo Video',
            'thumbnail_url' => 'https://i.vimeocdn.com/video/123456789.jpg',
        ]));

        $schema = $this->service->fetchSchema('https://vimeo.com/1172365854');

        $this->assertSame('My Vimeo Video', $schema['description']);
    }

    public function test_fetch_schema_returns_fallback_on_wp_error(): void
    {
        Functions\when('wp_remote_get')->justReturn(null);
        Functions\when('is_wp_error')->justReturn(true);

        $url = 'https://vimeo.com/1172365854';
        $schema = $this->service->fetchSchema($url);

        $this->assertSame('VideoObject', $schema['@type']);
        $this->assertSame('Vimeo Video', $schema['name']);
        $this->assertSame($url, $schema['embedUrl']);
    }

    public function test_fetch_schema_returns_fallback_when_response_has_no_title(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode([]));

        $schema = $this->service->fetchSchema('https://vimeo.com/1172365854');

        $this->assertSame('Vimeo Video', $schema['name']);
    }
}
