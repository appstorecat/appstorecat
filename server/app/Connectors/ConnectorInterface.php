<?php

namespace App\Connectors;

use App\Models\App;

interface ConnectorInterface
{
    public function supports(string $platform): bool;

    public function fetchIdentity(App $app, string $country = 'us'): ConnectorResult;

    public function fetchListings(App $app, string $country = 'us', ?string $language = null): ConnectorResult;

    public function fetchMetrics(App $app, string $country = 'us'): ConnectorResult;

    public function fetchDeveloperApps(string $developerExternalId): ConnectorResult;

    public function fetchSearch(string $term, int $limit = 10, string $country = 'us'): array;

    public function fetchChart(string $collection, string $country, ?string $categoryExternalId = null): array;

    public function getSourceName(): string;
}
