<?php

namespace Wefabric\WPVideoIndexer\Services;

class VimeoSchemaService implements VideoSchemaServiceInterface
{
    public function matches(string $url): bool
    {
        return str_contains($url, 'vimeo.com');
    }

    public function extractId(string $url): ?string
    {
        if (preg_match('/vimeo\.com\/(?:video\/|channels\/[^\/]+\/|[^\/]+\/)?(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function fetchSchema(string $url): array
    {
        $oembed_url = 'https://vimeo.com/api/oembed.json?url=' . urlencode($url);

        $response = wp_remote_get($oembed_url, ['timeout' => 2]);

        if (is_wp_error($response)) {
            return $this->fallbackSchema($url);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data) || empty($data['title'])) {
            return $this->fallbackSchema($url);
        }

        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => $data['title'],
            'description'  => !empty($data['description'])
                ? wp_strip_all_tags($data['description'])
                : $data['title'],
            'thumbnailUrl' => $data['thumbnail_url'] ?? '',
            'embedUrl'     => !empty($data['html']) ? $this->extractEmbedSrc($data['html']) : $url,
            'contentUrl'   => $url,
            'uploadDate'   => !empty($data['upload_date'])
                ? date('c', strtotime($data['upload_date']))
                : date('c'),
        ];
    }

    public function fallbackSchema(string $url): array
    {
        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'VideoObject',
            'name'         => 'Vimeo Video',
            'description'  => 'Video content',
            'thumbnailUrl' => '',
            'embedUrl'     => $url,
            'contentUrl'   => $url,
            'uploadDate'   => date('c'),
        ];
    }

    private function extractEmbedSrc(string $html): ?string
    {
        if (preg_match('/src="([^"]+)"/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
