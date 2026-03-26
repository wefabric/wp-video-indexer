<?php

namespace Wefabric\WPVideoIndexer\Services;

class YouTubeSchemaService implements VideoSchemaServiceInterface
{
    public function matches(string $url): bool
    {
        return strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false;
    }

    public function extractId(string $url): ?string
    {
        if (preg_match('/(?:youtube\.com\/(?:embed\/|v\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function fetchSchema(string $url): array
    {
        $watch_url  = 'https://www.youtube.com/watch?v=' . $this->extractId($url);
        $oembed_url = 'https://www.youtube.com/oembed?url=' . urlencode($watch_url) . '&format=json';

        $response = wp_remote_get($oembed_url, ['timeout' => 2]);

        if (is_wp_error($response)) {
            return $this->fallbackSchema($url);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['title'])) {
            return $this->fallbackSchema($url);
        }

        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => $data['title'],
            'description'  => $data['title'],
            'thumbnailUrl' => $data['thumbnail_url'] ?? '',
            'embedUrl'     => $url,
            'contentUrl'   => $watch_url,
            'uploadDate'   => date('c'),
        ];
    }

    public function fallbackSchema(string $url): array
    {
        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => 'YouTube Video',
            'description'  => 'Video content',
            'thumbnailUrl' => '',
            'embedUrl'     => $url,
            'contentUrl'   => $url,
            'uploadDate'   => date('c'),
        ];
    }
}
