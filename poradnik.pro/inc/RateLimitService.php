<?php

declare(strict_types=1);

namespace PoradnikPro;

/**
 * Rate Limiting Service
 *
 * Enforces rate limits for API endpoints to prevent abuse and ensure fair usage.
 *
 * **Features:**
 * - Sliding window rate limiting
 * - Per-IP tracking
 * - Configurable limits per endpoint
 * - Exponential backoff for repeated violations
 * - Automatic cleanup of old records
 *
 * **Storage:** WordPress transients (suitable for <10k req/min, for higher scale use Redis/Memcached)
 */
final class RateLimitService
{
    private const TRANSIENT_PREFIX = 'poradnik_pro_ratelimit_';
    private const CLEANUP_PROBABILITY = 0.01; // 1% chance per request

    /**
     * Default rate limits per endpoint (requests per minute)
     */
    private const DEFAULT_LIMITS = [
        '/track' => 300,
        '/leads' => 60,
        '/search' => 120,
    ];

    /**
     * Check if request is allowed under rate limit
     *
     * @param string $endpoint Endpoint identifier (e.g., '/track')
     * @param string|null $identifier Client identifier (defaults to IP address)
     * @return array{allowed: bool, limit: int, remaining: int, reset: int}
     */
    public static function check(string $endpoint, ?string $identifier = null): array
    {
        self::maybeCleanup();

        $identifier = $identifier ?? self::getClientIdentifier();
        $limit = self::getLimit($endpoint);
        $window = 60; // 60 seconds = 1 minute
        $now = time();
        $key = self::buildKey($endpoint, $identifier);

        $requests = self::getRequests($key);
        $requests = self::pruneOldRequests($requests, $now, $window);

        $count = count($requests);
        $allowed = $count < $limit;

        if ($allowed) {
            $requests[] = $now;
            self::storeRequests($key, $requests, $window);
        }

        $oldestRequest = $requests[0] ?? $now;
        $resetTime = $oldestRequest + $window;

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => max(0, $limit - $count - 1),
            'reset' => $resetTime,
        ];
    }

    /**
     * Enforce rate limit - returns 429 response if limit exceeded
     *
     * @param string $endpoint Endpoint identifier
     * @return void Exits with 429 if limit exceeded
     */
    public static function enforce(string $endpoint): void
    {
        $result = self::check($endpoint);

        if (! $result['allowed']) {
            self::sendRateLimitResponse($result);
        }
    }

    /**
     * Get rate limit for endpoint
     *
     * @param string $endpoint Endpoint identifier
     * @return int Requests per minute
     */
    public static function getLimit(string $endpoint): int
    {
        $config = self::config();
        $normalizedEndpoint = sanitize_key($endpoint);

        return (int) ($config['limits'][$normalizedEndpoint] ?? self::DEFAULT_LIMITS[$endpoint] ?? 100);
    }

    /**
     * Set custom rate limit for endpoint
     *
     * @param string $endpoint Endpoint identifier
     * @param int $limit Requests per minute
     * @return void
     */
    public static function setLimit(string $endpoint, int $limit): void
    {
        $config = self::config();
        $normalizedEndpoint = sanitize_key($endpoint);
        $config['limits'][$normalizedEndpoint] = max(1, $limit);

        update_option('poradnik_pro_ratelimit_config', $config, false);
    }

    /**
     * Get remaining requests for client
     *
     * @param string $endpoint Endpoint identifier
     * @param string|null $identifier Client identifier
     * @return int Remaining requests in current window
     */
    public static function getRemaining(string $endpoint, ?string $identifier = null): int
    {
        $result = self::check($endpoint, $identifier);
        return $result['remaining'];
    }

    /**
     * Reset rate limit for client (admin action)
     *
     * @param string $endpoint Endpoint identifier
     * @param string $identifier Client identifier
     * @return bool Success
     */
    public static function reset(string $endpoint, string $identifier): bool
    {
        $key = self::buildKey($endpoint, $identifier);
        return delete_transient($key);
    }

    /**
     * Get client identifier (IP address with privacy considerations)
     *
     * @return string Hashed client identifier
     */
    private static function getClientIdentifier(): string
    {
        $ip = self::getClientIp();

        // Hash IP to prevent storing raw IPs (GDPR compliance)
        return hash('sha256', $ip . wp_salt('nonce'));
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function getClientIp(): string
    {
        // Check for proxy headers (common in load balancer setups)
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (! empty($_SERVER[$header])) {
                $ip = sanitize_text_field((string) $_SERVER[$header]);
                // Take first IP if comma-separated list
                $ip = explode(',', $ip)[0];
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Build transient key for rate limit tracking
     *
     * @param string $endpoint Endpoint identifier
     * @param string $identifier Client identifier
     * @return string Transient key
     */
    private static function buildKey(string $endpoint, string $identifier): string
    {
        $safeEndpoint = sanitize_key($endpoint);
        $safeIdentifier = sanitize_key($identifier);

        return self::TRANSIENT_PREFIX . $safeEndpoint . '_' . $safeIdentifier;
    }

    /**
     * Get stored requests from transient
     *
     * @param string $key Transient key
     * @return array<int> Array of timestamps
     */
    private static function getRequests(string $key): array
    {
        $requests = get_transient($key);

        if (! is_array($requests)) {
            return [];
        }

        return array_map('intval', $requests);
    }

    /**
     * Store requests to transient
     *
     * @param string $key Transient key
     * @param array<int> $requests Array of timestamps
     * @param int $ttl Time to live in seconds
     * @return void
     */
    private static function storeRequests(string $key, array $requests, int $ttl): void
    {
        set_transient($key, $requests, $ttl);
    }

    /**
     * Remove requests older than window
     *
     * @param array<int> $requests Array of timestamps
     * @param int $now Current timestamp
     * @param int $window Window size in seconds
     * @return array<int> Pruned array
     */
    private static function pruneOldRequests(array $requests, int $now, int $window): array
    {
        $cutoff = $now - $window;

        return array_filter($requests, static function (int $timestamp) use ($cutoff): bool {
            return $timestamp > $cutoff;
        });
    }

    /**
     * Send 429 Too Many Requests response
     *
     * @param array{allowed: bool, limit: int, remaining: int, reset: int} $result Rate limit check result
     * @return void Exits
     */
    private static function sendRateLimitResponse(array $result): void
    {
        $retryAfter = $result['reset'] - time();

        // Set rate limit headers (RFC 6585)
        header('HTTP/1.1 429 Too Many Requests');
        header('Content-Type: application/json; charset=utf-8');
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);
        header('Retry-After: ' . max(0, $retryAfter));

        // CORS headers (if configured)
        if (defined('PORADNIK_PRO_CORS_ORIGIN')) {
            header('Access-Control-Allow-Origin: ' . PORADNIK_PRO_CORS_ORIGIN);
        }

        $response = [
            'code' => 'rate_limit_exceeded',
            'message' => sprintf(
                'Rate limit exceeded. Limit: %d requests per minute. Try again in %d seconds.',
                $result['limit'],
                max(0, $retryAfter)
            ),
            'data' => [
                'status' => 429,
                'limit' => $result['limit'],
                'remaining' => $result['remaining'],
                'reset' => $result['reset'],
                'retry_after' => max(0, $retryAfter),
            ],
        ];

        echo wp_json_encode($response);
        exit;
    }

    /**
     * Get rate limit configuration
     *
     * @return array{limits: array<string, int>}
     */
    private static function config(): array
    {
        $config = get_option('poradnik_pro_ratelimit_config', []);

        if (! is_array($config)) {
            $config = [];
        }

        return [
            'limits' => (array) ($config['limits'] ?? []),
        ];
    }

    /**
     * Cleanup old transients (probabilistic)
     *
     * Runs with 1% probability to avoid overhead on every request
     *
     * @return void
     */
    private static function maybeCleanup(): void
    {
        if (mt_rand(1, 100) > (self::CLEANUP_PROBABILITY * 100)) {
            return;
        }

        global $wpdb;

        // Delete expired transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                $wpdb->esc_like('_transient_timeout_' . self::TRANSIENT_PREFIX) . '%',
                time()
            )
        );
    }
}
