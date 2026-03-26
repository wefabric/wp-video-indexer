<?php

namespace Wefabric\WPVideoIndexer\Services;

interface VideoSchemaServiceInterface
{
    public function matches(string $url): bool;

    public function extractId(string $url): ?string;

    public function fetchSchema(string $url): array;
}
