<?php

declare(strict_types=1);

namespace PoradnikPro;

use WP_Error;

final class ApiClient
{
    private const DEFAULT_TIMEOUT = 8;

    public static function get(string $path, array $query = []): array
    {
        $url = rest_url('peartree/v1' . $path);
        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        $response = wp_remote_get($url, [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => self::headers(),
        ]);

        return self::normalizeResponse($response);
    }

    public static function post(string $path, array $body): array
    {
        $response = wp_remote_post(rest_url('peartree/v1' . $path), [
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => self::headers(),
            'body' => wp_json_encode($body),
        ]);

        return self::normalizeResponse($response);
    }

    private static function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest'),
        ];
    }

    private static function normalizeResponse(array|WP_Error $response): array
    {
        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'status' => 500,
                'data' => [],
                'error' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => is_array($data) ? $data : [],
            'error' => $status >= 200 && $status < 300 ? null : __('API request failed.', 'poradnik-pro'),
        ];
    }
}