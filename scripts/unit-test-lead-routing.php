#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract tests for LeadRouter multi/exclusive routing behavior.
 *
 * Usage:
 *   php scripts/unit-test-lead-routing.php
 */

$capturedRemotePosts = [];
$mockRemotePostResponses = [];

if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (! function_exists('filter_var')) {
    function filter_var($value, $filter = FILTER_DEFAULT, $options = null) {
        if ($filter === 273) { // FILTER_VALIDATE_URL
            // Simple URL validation
            return (preg_match('~^https?://~', $value) ? $value : false);
        }
        return $value;
    }
}

if (! defined('FILTER_VALIDATE_URL')) {
    define('FILTER_VALIDATE_URL', 273);
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $cap): bool
    {
        return true;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $key, mixed $default = ''): mixed
    {
        global $mockLeadPartners;
        if ($key === 'peartree_lead_partners') {
            return $mockLeadPartners ?? [];
        }
        return $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $key, mixed $value): bool
    {
        global $mockLeadPartners;
        if ($key === 'peartree_lead_partners') {
            $mockLeadPartners = $value;
            return true;
        }
        return false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expires = 0): bool
    {
        global $mockTransients;
        if (! isset($mockTransients)) {
            $mockTransients = [];
        }
        $mockTransients[$key] = $value;
        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        global $mockTransients;
        return $mockTransients[$key] ?? false;
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = ''
        ) {}

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return $value instanceof WP_Error;
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array
    {
        global $capturedRemotePosts;
        global $mockRemotePostResponses;

        $capturedRemotePosts[] = [
            'url' => $url,
            'args' => $args,
        ];

        // Return mock response if configured, otherwise success
        if (isset($mockRemotePostResponses[$url])) {
            return array_shift($mockRemotePostResponses[$url]);
        }

        return [
            'response' => ['code' => 200],
            'body' => '{"ok":true}',
        ];
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return (string) ($response['body'] ?? '');
    }
}

// Load SlaMonitor first (with mocked WordPress functions)
require_once __DIR__ . '/../poradnik.pro/inc/SlaMonitor.php';

require_once __DIR__ . '/../poradnik.pro/inc/LeadRouter.php';

use PoradnikPro\LeadRouter;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' (expected: ' . var_export($expected, true) . ', got: ' . var_export($actual, true) . ')');
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function resetLeadRoutingTestState(): void
{
    global $capturedRemotePosts;
    global $mockRemotePostResponses;
    global $mockLeadPartners;
    global $mockTransients;

    $capturedRemotePosts = [];
    $mockRemotePostResponses = [];
    $mockLeadPartners = [];
    $mockTransients = [];
}

function testLeadRouterMultiBroadcastToAllMatchingPartners(): void
{
    global $mockRemotePostResponses;

    resetLeadRoutingTestState();

    $partners = [
        [
            'id' => 'firm-a',
            'endpoint' => 'https://firm-a.example.com/leads',
            'api_key' => 'key-a',
        ],
        [
            'id' => 'firm-b',
            'endpoint' => 'https://firm-b.example.com/leads',
            'api_key' => 'key-b',
        ],
    ];

    $mockRemotePostResponses['https://firm-a.example.com/leads'] = [
        ['response' => ['code' => 201], 'body' => '{"ok":true}'],
    ];
    $mockRemotePostResponses['https://firm-b.example.com/leads'] = [
        ['response' => ['code' => 201], 'body' => '{"ok":true}'],
    ];

    $lead = [
        'name' => 'Jan Multi',
        'email_or_phone' => 'jan@example.com',
        'problem' => 'Housing services',
        'location' => 'Warszawa',
    ];

    $result = LeadRouter::routeLead($lead, [
        'mode' => 'multi',
        'partners' => $partners,
    ]);

    assertSame(true, (bool) ($result['ok'] ?? false), 'Multi mode should succeed when both partners accept');
    assertSame('multi', $result['mode'], 'Mode should be multi');
    assertSame(2, (int) ($result['routed_count'] ?? 0), 'Multi mode should route to both partners');
    assertSame(2, count($result['routes'] ?? []), 'Multi mode should have routes for both partners');

    echo "✓ LeadRouter::routeLead multi-broadcast contract\n";
}

function testLeadRouterExclusiveRoutesToFirstMatchingPartner(): void
{
    global $capturedRemotePosts;
    global $mockRemotePostResponses;

    resetLeadRoutingTestState();

    $partners = [
        [
            'id' => 'firm-a',
            'endpoint' => 'https://firm-a.example.com/leads',
            'api_key' => 'key-a',
        ],
        [
            'id' => 'firm-b',
            'endpoint' => 'https://firm-b.example.com/leads',
            'api_key' => 'key-b',
        ],
    ];

    $mockRemotePostResponses['https://firm-a.example.com/leads'] = [
        ['response' => ['code' => 201], 'body' => '{"ok":true}'],
    ];
    $mockRemotePostResponses['https://firm-b.example.com/leads'] = [
        ['response' => ['code' => 201], 'body' => '{"ok":true}'],
    ];

    $lead = [
        'name' => 'Jan Exclusive',
        'email_or_phone' => 'jan@example.com',
        'problem' => 'Legal advice',
        'location' => 'Krakow',
    ];

    $result = LeadRouter::routeLead($lead, [
        'mode' => 'exclusive',
        'partners' => $partners,
    ]);

    assertSame(true, (bool) ($result['ok'] ?? false), 'Exclusive mode should succeed with one partner');
    assertSame('exclusive', $result['mode'], 'Mode should be exclusive');
    assertSame(1, (int) ($result['routed_count'] ?? 0), 'Exclusive mode should route to only one partner');
    assertSame(1, count($result['routes'] ?? []), 'Exclusive mode should have route for only first partner');
    assertSame(1, count($capturedRemotePosts ?? []), 'Exclusive mode should make only one HTTP request');

    echo "✓ LeadRouter::routeLead exclusive-first contract\n";
}

function testLeadRouterLocationFilterHandling(): void
{
    resetLeadRoutingTestState();

    $partners = [
        [
            'id' => 'warsaw-only',
            'endpoint' => 'https://warsaw.example.com/leads',
            'api_key' => 'key',
            'location_filter' => ['Warsaw', 'Warszawa'],
        ],
        [
            'id' => 'krakow-only',
            'endpoint' => 'https://krakow.example.com/leads',
            'api_key' => 'key',
            'location_filter' => ['Krakow', 'Krakow'],
        ],
    ];

    $leadWarsaw = [
        'name' => 'User Warsaw',
        'email_or_phone' => 'warsaw@example.com',
        'problem' => 'Legal',
        'location' => 'Warszawa',
    ];

    $result = LeadRouter::routeLead($leadWarsaw, [
        'mode' => 'multi',
        'partners' => $partners,
    ]);

    assertSame(1, count($result['routes'] ?? []), 'Location filter should match only warsaw-only partner');

    echo "✓ LeadRouter::routeLead location-filter contract\n";
}

function testLeadRouterValidatesPartnerConfig(): void
{
    resetLeadRoutingTestState();

    $validPartner = [
        'id' => 'test',
        'endpoint' => 'https://example.com/api',
        'api_key' => 'test-key',
    ];

    assertTrue(LeadRouter::validatePartnerConfig($validPartner), 'Valid partner config should pass');

    $invalidPartner = [
        'id' => 'test',
        'endpoint' => 'not-a-url',
        'api_key' => 'test-key',
    ];

    assertTrue(! LeadRouter::validatePartnerConfig($invalidPartner), 'Invalid endpoint URL should fail');

    $missingKey = [
        'id' => 'test',
        'endpoint' => 'https://example.com/api',
    ];

    assertTrue(! LeadRouter::validatePartnerConfig($missingKey), 'Missing api_key should fail');

    echo "✓ LeadRouter::validatePartnerConfig contract\n";
}

function testLeadRouterHandlesNoPartnerConfiguration(): void
{
    resetLeadRoutingTestState();

    $lead = [
        'name' => 'Test',
        'email_or_phone' => 'test@example.com',
        'problem' => 'Test',
        'location' => 'Test',
    ];

    $result = LeadRouter::routeLead($lead, [
        'mode' => 'multi',
        'partners' => [],
    ]);

    assertSame(false, (bool) ($result['ok'] ?? true), 'Should fail with no partners');
    assertSame('no_partners_configured', $result['error'] ?? '', 'Should report no partners error');

    echo "✓ LeadRouter::routeLead no-partners contract\n";
}

try {
    echo "Lead routing unit tests\n\n";

    testLeadRouterMultiBroadcastToAllMatchingPartners();
    testLeadRouterExclusiveRoutesToFirstMatchingPartner();
    testLeadRouterLocationFilterHandling();
    testLeadRouterValidatesPartnerConfig();
    testLeadRouterHandlesNoPartnerConfiguration();

    echo "\nOverall: PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n");
    exit(1);
}
