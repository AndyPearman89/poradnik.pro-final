<?php

declare(strict_types=1);

namespace PoradnikPro;

final class LeadService
{
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

        $response = ApiClient::post('/leads', $sanitized);

        return [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'message' => $response['ok']
                ? __('Lead sent successfully.', 'poradnik-pro')
                : __('Could not send lead. Try again.', 'poradnik-pro'),
            'error' => $response['error'],
        ];
    }
}
