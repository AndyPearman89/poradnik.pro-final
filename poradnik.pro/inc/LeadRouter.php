<?php

declare(strict_types=1);

namespace PoradnikPro;

final class LeadRouter
{
    private const ROUTING_MODE_MULTI = 'multi';
    private const ROUTING_MODE_EXCLUSIVE = 'exclusive';

    /**
     * Route a lead to configured partners based on mode and rules.
     *
     * For multi mode: Attempt to send to all matching partners (best-effort broadcast).
     * For exclusive: Attempt to send to first matching partner and skip others.
     *
     * Returns routing result with success/failure per partner.
     */
    public static function routeLead(array $lead, array $config = []): array
    {
        $mode = $config['mode'] ?? self::ROUTING_MODE_MULTI;
        $partners = $config['partners'] ?? [];

        if ($partners === []) {
            return [
                'ok' => false,
                'mode' => $mode,
                'routed_count' => 0,
                'routes' => [],
                'error' => 'no_partners_configured',
            ];
        }

        $matches = self::findMatchingPartners($lead, $partners);

        if ($matches === []) {
            return [
                'ok' => false,
                'mode' => $mode,
                'routed_count' => 0,
                'routes' => [],
                'error' => 'no_matching_partners',
            ];
        }

        $routes = [];
        $routedCount = 0;

        if ($mode === self::ROUTING_MODE_EXCLUSIVE) {
            $matches = [array_shift($matches)];
        }

        foreach ($matches as $partner) {
            $result = self::sendToPartner($lead, $partner);
            $routes[] = [
                'partner_id' => $partner['id'] ?? 'unknown',
                'ok' => $result['ok'] ?? false,
                'status' => $result['status'] ?? 500,
                'error' => $result['error'] ?? null,
            ];

            if ($result['ok'] ?? false) {
                $routedCount++;
            }
        }

        return [
            'ok' => $routedCount > 0,
            'mode' => $mode,
            'routed_count' => $routedCount,
            'routes' => $routes,
            'error' => $routedCount > 0 ? null : 'no_successful_routes',
        ];
    }

    /**
     * Find partners matching the lead's intent/location/category.
     */
    private static function findMatchingPartners(array $lead, array $partners): array
    {
        return array_values(array_filter($partners, static function (array $partner) use ($lead): bool {
            // Check location filter
            if (! empty($partner['location_filter']) && $partner['location_filter'] !== []) {
                $leadLocation = strtolower(trim($lead['location'] ?? ''));
                $hasMatch = false;
                foreach ($partner['location_filter'] as $allowedLoc) {
                    if (stripos($leadLocation, $allowedLoc) !== false) {
                        $hasMatch = true;
                        break;
                    }
                }
                if (! $hasMatch) {
                    return false;
                }
            }

            // Check category filter
            if (! empty($partner['category_filter'])) {
                $leadProblem = strtolower(trim($lead['problem'] ?? ''));
                if (stripos($leadProblem, $partner['category_filter']) === false) {
                    return false;
                }
            }

            // Check capacity / daily limits
            if (! empty($partner['daily_limit'])) {
                $routedToday = (int) (get_transient('peartree_routed_' . $partner['id']) ?? 0);
                if ($routedToday >= (int) $partner['daily_limit']) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Send lead to a specific partner via its endpoint.
     */
    private static function sendToPartner(array $lead, array $partner): array
    {
        $endpoint = $partner['endpoint'] ?? '';
        if (! $endpoint || ! filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return [
                'ok' => false,
                'status' => 400,
                'error' => 'invalid_endpoint',
            ];
        }

        $payload = [
            'name' => $lead['name'] ?? '',
            'email_or_phone' => $lead['email_or_phone'] ?? '',
            'problem' => $lead['problem'] ?? '',
            'location' => $lead['location'] ?? '',
            'partner_id' => $partner['id'] ?? 'unknown',
            'source' => 'peartree',
            'timestamp' => gmdate('c'),
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => (int) ($partner['timeout'] ?? 10),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . ($partner['api_key'] ?? ''),
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'status' => 500,
                'error' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $ok = $status >= 200 && $status < 300;

        if ($ok && ! empty($partner['id']) && ! empty($partner['daily_limit'])) {
            $routedToday = (int) (get_transient('peartree_routed_' . $partner['id']) ?? 0);
            set_transient('peartree_routed_' . $partner['id'], $routedToday + 1, 86400);
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'error' => $ok ? null : __('Partner endpoint rejected request.', 'poradnik-pro'),
        ];
    }

    /**
     * Get configured partners from WordPress options.
     */
    public static function getConfiguredPartners(): array
    {
        $config = get_option('peartree_lead_partners', []);
        return is_array($config) ? $config : [];
    }

    /**
     * Set configured partners (admin only).
     */
    public static function setConfiguredPartners(array $partners): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        update_option('peartree_lead_partners', $partners);
    }

    /**
     * Validate partner configuration structure.
     */
    public static function validatePartnerConfig(array $partner): bool
    {
        $required = ['id', 'endpoint', 'api_key'];
        foreach ($required as $key) {
            if (empty($partner[$key])) {
                return false;
            }
        }

        if (! filter_var($partner['endpoint'], FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }
}
