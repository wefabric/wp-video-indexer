<?php

namespace Wefabric\WPVideoIndexer\Services;

class VideoSchemaOutputService
{
    public function output(): void
    {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $schemas = get_post_meta($post->ID, VideoSchemaCacheService::META_KEY, true);

        if (empty($schemas)) {
            return;
        }

        foreach ($schemas as $schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
        }
    }
}
