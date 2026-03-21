<?php

declare(strict_types=1);

namespace PoradnikPro;

final class LeadService
{
    private const MAX_RETRIES = 2;
    private const BACKOFF_MS = 250;

    public static function submit(array $payload): array
    {
        if (! empty($payload['website'])) {
            return [
                'ok' => true,
                'status' => 202,
                'message' => __('Lead accepted.', 'poradnik-pro'),
            ];
        }

        $sanitized = [
            'name' => sanitize_text_field((string) ($payload['name'] ?? '')),
            'email_or_phone' => sanitize_text_field((string) ($payload['email_or_phone'] ?? '')),
            'problem' => sanitize_textarea_field((string) ($payload['problem'] ?? '')),
            'location' => sanitize_text_field((string) ($payload['location'] ?? '')),
        ];

        $response = self::postWithRetry('/leads', $sanitized);

        return [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'attempts' => $response['attempts'] ?? 1,
            'message' => $response['ok']
                ? __('Lead sent successfully.', 'poradnik-pro')
                : __('Could not send lead. Try again.', 'poradnik-pro'),
            'error' => $response['error'],
        ];
    }

    private static function postWithRetry(string $path, array $body): array
    {
        $attempt = 0;
        $last = [
            'ok' => false,
            'status' => 500,
            'data' => [],
            'error' => __('API request failed.', 'poradnik-pro'),
        ];

        while ($attempt <= self::MAX_RETRIES) {
            $attempt++;
            $last = ApiClient::post($path, $body);
            if (! self::shouldRetry($last, $attempt)) {
                break;
            }

            $backoffMs = self::BACKOFF_MS * $attempt;
            usleep($backoffMs * 1000);
        }

        $last['attempts'] = $attempt;
        return $last;
    }

    private static function shouldRetry(array $response, int $attempt): bool
    {
        if ($attempt > self::MAX_RETRIES) {
            return false;
        }

        if ((bool) ($response['ok'] ?? false)) {
            return false;
        }

        $status = (int) ($response['status'] ?? 0);
        if ($status >= 500 || $status === 0) {
            return true;
        }

        return false;
    }
}
