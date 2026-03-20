<?php

declare(strict_types=1);

namespace PoradnikPro;

final class SearchService
{
    public static function search(string $query): array
    {
        if (trim($query) === '') {
            return [
                'guides' => [],
                'specialists' => [],
                'rankings' => [],
            ];
        }

        $response = ApiClient::get('/search', ['q' => $query]);

        if (! $response['ok']) {
            return [
                'guides' => [],
                'specialists' => [],
                'rankings' => [],
                'error' => $response['error'],
            ];
        }

        return [
            'guides' => (array) ($response['data']['guides'] ?? []),
            'specialists' => (array) ($response['data']['specialists'] ?? []),
            'rankings' => (array) ($response['data']['rankings'] ?? []),
        ];
    }
}
