<?php

namespace Wefabric\WPVideoIndexer\Hooks;

use Themosis\Hook\Hookable;
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
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        $content = get_post_field('post_content', $post_id);
        if (!$content) {
            return;
        }

        preg_match_all('/https?:\/\/[^\s"]+/', $content, $matches);
        if (empty($matches[0])) {
            return;
        }

        $services = array_map(fn($class) => new $class(), $this->services);
        $schemas = [];
        $seen = [];

        foreach ($matches[0] as $url) {
            foreach ($services as $service) {
                if (!$service->matches($url)) {
                    continue;
                }

                $id = $service->extractId($url);
                $key = get_class($service) . ':' . $id;

                if (!$id || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $schemas[] = $service->fetchSchema($url);
            }
        }

        update_post_meta($post_id, '_cached_video_schemas', $schemas);
    }

    public function outputCachedVideoSchemas(): void
    {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $schemas = get_post_meta($post->ID, '_cached_video_schemas', true);

        if (empty($schemas)) {
            return;
        }

        foreach ($schemas as $schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
        }
    }
}
