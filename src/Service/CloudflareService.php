<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class CloudflareService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $apiKey,
        private string $zoneId,
    ) {
    }

    public function isServiceAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->zoneId);
    }

    public function purgeCache(): void
    {
        $this->client->request('POST', "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'purge_everything' => true,
            ],
        ]);
    }
}
