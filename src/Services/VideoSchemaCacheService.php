<?php

namespace Wefabric\WPVideoIndexer\Services;

class VideoSchemaCacheService
{
    public const META_KEY = '_cached_video_schemas';

    /** @var VideoSchemaServiceInterface[] */
    private array $services;

    /**
     * @param class-string<VideoSchemaServiceInterface>[] $serviceClasses
     */
    public function __construct(array $serviceClasses)
    {
        $this->services = array_map(fn($class) => new $class(), $serviceClasses);
    }

    public function cache(int $post_id): void
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

        $existing = get_post_meta($post_id, self::META_KEY, true) ?: [];
        $schemas = [];
        $seen = [];

        foreach ($matches[0] as $url) {
            foreach ($this->services as $service) {
                if (!$service->matches($url)) {
                    continue;
                }

                $id = $service->extractId($url);
                $key = get_class($service) . ':' . $id;

                if (!$id || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $schemas[$url] = $existing[$url] ?? $service->fetchSchema($url);
            }
        }

        update_post_meta($post_id, self::META_KEY, $schemas);
    }
}
