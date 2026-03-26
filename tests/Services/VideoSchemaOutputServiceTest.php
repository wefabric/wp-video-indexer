<?php

namespace Wefabric\WPVideoIndexer\Tests\Services;

use Brain\Monkey\Functions;
use Wefabric\WPVideoIndexer\Services\VideoSchemaCacheService;
use Wefabric\WPVideoIndexer\Services\VideoSchemaOutputService;
use Wefabric\WPVideoIndexer\Tests\TestCase;

class VideoSchemaOutputServiceTest extends TestCase
{
    private VideoSchemaOutputService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VideoSchemaOutputService();
    }

    public function test_outputs_schema_tags_for_each_cached_schema(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'VideoObject',
            'name'     => 'Never Gonna Give You Up',
            'embedUrl' => $url,
            'contentUrl' => $url,
        ];

        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_post_meta')->justReturn([$url => $schema]);
        Functions\when('wp_json_encode')->alias('json_encode');

        $GLOBALS['post'] = (object) ['ID' => 1];

        ob_start();
        $this->service->output();
        $output = ob_get_clean();

        unset($GLOBALS['post']);

        $this->assertStringContainsString('<script type="application/ld+json">', $output);
        $this->assertStringContainsString('Never Gonna Give You Up', $output);
    }

    public function test_does_not_output_on_non_singular_pages(): void
    {
        Functions\when('is_singular')->justReturn(false);

        ob_start();
        $this->service->output();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function test_does_not_output_when_no_schemas_cached(): void
    {
        Functions\when('is_singular')->justReturn(true);
        Functions\when('get_post_meta')->justReturn([]);

        $GLOBALS['post'] = (object) ['ID' => 1];

        ob_start();
        $this->service->output();
        $output = ob_get_clean();

        unset($GLOBALS['post']);

        $this->assertSame('', $output);
    }

    public function test_reads_from_correct_meta_key(): void
    {
        Functions\when('is_singular')->justReturn(true);
        Functions\expect('get_post_meta')
            ->once()
            ->andReturnUsing(function ($post_id, $key) {
                $this->assertSame(VideoSchemaCacheService::META_KEY, $key);
                return [];
            });

        $GLOBALS['post'] = (object) ['ID' => 1];
        $this->service->output();
        unset($GLOBALS['post']);
    }
}
