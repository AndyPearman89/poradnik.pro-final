#!/usr/bin/env php
<?php

declare(strict_types=1);

ob_start();

/**
 * Lightweight unit tests for service classes without PHPUnit.
 *
 * Usage:
 *   php scripts/unit-test-services.php
 */

$capturedRemotePost = null;
$mockRemotePostResponse = null;
$mockOptions = [];
$capturedRestRouteRegistration = null;

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

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        global $mockOptions;
        return array_key_exists($name, $mockOptions) ? $mockOptions[$name] : $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value, bool $autoload = false): bool
    {
        global $mockOptions;
        $mockOptions[$name] = $value;
        return true;
    }
}

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): bool
    {
        global $capturedRestRouteRegistration;
        $capturedRestRouteRegistration = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $args,
        ];

        return true;
    }
}

if (! function_exists('rest_url')) {
    function rest_url(string $path): string
    {
        return 'http://localhost:8080/wp-json/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        return 'test-nonce';
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function get_error_message(): string
        {
            return 'error';
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
        global $capturedRemotePost;
        global $mockRemotePostResponse;
        $capturedRemotePost = [
            'url' => $url,
            'args' => $args,
        ];

        if (is_array($mockRemotePostResponse)) {
            return $mockRemotePostResponse;
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

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private array $jsonParams = [])
        {
        }

        public function get_json_params(): array
        {
            return $this->jsonParams;
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(private array $data = [], private int $status = 200)
        {
        }

        public function get_data(): array
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

require_once __DIR__ . '/../poradnik.pro/inc/ApiClient.php';
require_once __DIR__ . '/../poradnik.pro/inc/LeadService.php';
require_once __DIR__ . '/../poradnik.pro/inc/AnalyticsService.php';

use PoradnikPro\AnalyticsService;
use PoradnikPro\LeadService;

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")");
    }
}

function testPruneStoreRemovesOldDays(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'pruneStore');
    $method->setAccessible(true);

    $today = gmdate('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime('-1 day UTC'));
    $old = gmdate('Y-m-d', strtotime('-5 days UTC'));

    $store = [
        $old => ['events' => ['a' => 1]],
        $yesterday => ['events' => ['b' => 1]],
        $today => ['events' => ['c' => 1]],
    ];

    $result = $method->invoke(null, $store, 2);

    assertTrue(! isset($result[$old]), 'pruneStore should remove records older than retention window');
    assertTrue(isset($result[$yesterday]), 'pruneStore should keep yesterday for 2-day window');
    assertTrue(isset($result[$today]), 'pruneStore should keep today');

    echo "✓ AnalyticsService::pruneStore retention logic\n";
}

function testPruneStoreRetentionOneKeepsOnlyToday(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'pruneStore');
    $method->setAccessible(true);

    $today = gmdate('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime('-1 day UTC'));

    $store = [
        $yesterday => ['events' => ['old' => 1]],
        $today => ['events' => ['new' => 1]],
    ];

    $result = $method->invoke(null, $store, 1);

    assertTrue(! isset($result[$yesterday]), 'retention_days=1 should remove yesterday');
    assertTrue(isset($result[$today]), 'retention_days=1 should keep today');

    echo "✓ AnalyticsService::pruneStore retention_days=1 edge-case\n";
}

function testLeadServiceSanitizesPayloadBeforeApiCall(): void
{
    global $capturedRemotePost;
    $capturedRemotePost = null;

    $payload = [
        'name' => " <b>Jan</b> ",
        'email_or_phone' => "  jan@example.com  ",
        'problem' => " <script>alert(1)</script> Potrzebuje pomocy ",
        'location' => "  Krakow  ",
        'website' => '',
    ];

    $response = LeadService::submit($payload);

    assertTrue($response['ok'] === true, 'LeadService should return ok=true for successful API response');
    assertSame(200, $response['status'], 'LeadService should return status from ApiClient');
    assertTrue(is_array($capturedRemotePost), 'LeadService should call ApiClient::post for non-honeypot payload');

    $body = json_decode((string) ($capturedRemotePost['args']['body'] ?? '{}'), true);
    assertTrue(is_array($body), 'LeadService should send JSON body');
    assertSame('Jan', $body['name'] ?? null, 'LeadService should sanitize name');
    assertSame('jan@example.com', $body['email_or_phone'] ?? null, 'LeadService should sanitize email_or_phone');
    assertSame('alert(1) Potrzebuje pomocy', $body['problem'] ?? null, 'LeadService should sanitize problem');
    assertSame('Krakow', $body['location'] ?? null, 'LeadService should sanitize location');

    echo "✓ LeadService::submit payload sanitization\n";
}

function testLeadServiceHoneypotShortCircuitsApiCall(): void
{
    global $capturedRemotePost;
    $capturedRemotePost = null;

    $response = LeadService::submit([
        'website' => 'bot-filled-field',
        'name' => 'Bot',
    ]);

    assertSame(true, $response['ok'], 'Honeypot payload should return ok=true');
    assertSame(202, $response['status'], 'Honeypot payload should return status 202');
    assertTrue($capturedRemotePost === null, 'Honeypot payload should skip API call');

    echo "✓ LeadService::submit honeypot short-circuit\n";
}

function testLeadServiceHandlesApiFailure(): void
{
    global $capturedRemotePost;
    global $mockRemotePostResponse;

    $capturedRemotePost = null;
    $mockRemotePostResponse = [
        'response' => ['code' => 500],
        'body' => '{"ok":false}',
    ];

    $response = LeadService::submit([
        'website' => '',
        'name' => 'Jan',
        'email_or_phone' => 'jan@example.com',
        'problem' => 'Test',
        'location' => 'Krakow',
    ]);

    assertSame(false, $response['ok'], 'LeadService should return ok=false when API fails');
    assertSame(500, $response['status'], 'LeadService should expose API failure status');
    assertSame('Could not send lead. Try again.', $response['message'], 'LeadService should return failure message');
    assertSame('API request failed.', $response['error'], 'LeadService should expose normalized API error');
    assertTrue(is_array($capturedRemotePost), 'LeadService should still attempt API call on non-honeypot payload');

    $mockRemotePostResponse = null;

    echo "✓ LeadService::submit API error handling\n";
}

function testAnalyticsServiceIngestEventRevenueMath(): void
{
    global $mockOptions;

    $mockOptions = [
        'poradnik_pro_kpi_store' => [],
        'poradnik_pro_kpi_config' => [
            'affiliate_value_per_click' => 2.5,
            'lead_value_per_success' => 30.0,
            'retention_days' => 30,
        ],
    ];

    $ctaRequest = new WP_REST_Request([
        'eventName' => 'cta_click',
        'payload' => [
            'source' => 'affiliate',
        ],
    ]);
    $ctaResponse = AnalyticsService::ingestEvent($ctaRequest);

    assertSame(202, $ctaResponse->get_status(), 'ingestEvent should return accepted status');
    assertSame(true, ($ctaResponse->get_data()['success'] ?? false), 'ingestEvent should return success=true');

    $leadRequest = new WP_REST_Request([
        'eventName' => 'lead_submit_success',
        'payload' => [
            'source' => 'organic',
        ],
    ]);
    AnalyticsService::ingestEvent($leadRequest);

    $day = gmdate('Y-m-d');
    $store = $mockOptions['poradnik_pro_kpi_store'] ?? [];
    $revenue = $store[$day]['revenue'] ?? [];

    assertSame(1, (int) ($revenue['affiliate_clicks'] ?? 0), 'ingestEvent should increment affiliate_clicks for affiliate cta_click');
    assertSame(2.5, (float) ($revenue['estimated_affiliate_revenue'] ?? 0), 'ingestEvent should add configured affiliate revenue');
    assertSame(1, (int) ($revenue['lead_success'] ?? 0), 'ingestEvent should increment lead_success for lead_submit_success');
    assertSame(30.0, (float) ($revenue['estimated_lead_revenue'] ?? 0), 'ingestEvent should add configured lead revenue');

    echo "✓ AnalyticsService::ingestEvent revenue math\n";
}

function testAnalyticsServiceIngestEventHandlesInvalidPayload(): void
{
    global $mockOptions;

    $mockOptions = [
        'poradnik_pro_kpi_store' => [],
        'poradnik_pro_kpi_config' => [
            'affiliate_value_per_click' => 1.5,
            'lead_value_per_success' => 25.0,
            'retention_days' => 30,
        ],
    ];

    $request = new WP_REST_Request([
        // no eventName, no payload.source
        'payload' => [],
    ]);

    $response = AnalyticsService::ingestEvent($request);
    assertSame(202, $response->get_status(), 'ingestEvent should accept invalid payload with defaults');

    $data = $response->get_data();
    assertSame(true, (bool) ($data['success'] ?? false), 'ingestEvent should return success=true for defaulted payload');
    assertSame('unknown', (string) ($data['event'] ?? ''), 'ingestEvent should fallback eventName to unknown');

    $day = gmdate('Y-m-d');
    $store = $mockOptions['poradnik_pro_kpi_store'] ?? [];

    assertSame(1, (int) ($store[$day]['events']['unknown'] ?? 0), 'ingestEvent should count unknown event');
    assertSame(1, (int) ($store[$day]['sources']['unknown'] ?? 0), 'ingestEvent should count unknown source');

    echo "✓ AnalyticsService::ingestEvent invalid payload resilience\n";
}

function testAnalyticsServiceRegistersPermissionCallback(): void
{
    global $capturedRestRouteRegistration;
    $capturedRestRouteRegistration = null;

    AnalyticsService::registerRestRoutes();

    assertTrue(is_array($capturedRestRouteRegistration), 'registerRestRoutes should call register_rest_route');
    assertSame('peartree/v1', $capturedRestRouteRegistration['namespace'] ?? null, 'registerRestRoutes should use correct namespace');
    assertSame('/track', $capturedRestRouteRegistration['route'] ?? null, 'registerRestRoutes should use correct route');

    $permission = $capturedRestRouteRegistration['args']['permission_callback'] ?? null;
    assertSame([AnalyticsService::class, 'checkTrackingPermission'], $permission, 'permission_callback should point to AnalyticsService::checkTrackingPermission');

    echo "✓ AnalyticsService::registerRestRoutes permission callback contract\n";
}

function testAnalyticsServiceSetsSecurityHeadersContract(): void
{
    if (! function_exists('xdebug_get_headers')) {
        echo "✓ AnalyticsService::ingestEvent security headers contract (skipped: xdebug_get_headers unavailable)\n";
        return;
    }

    global $mockOptions;
    $mockOptions = [
        'poradnik_pro_kpi_store' => [],
        'poradnik_pro_kpi_config' => [
            'affiliate_value_per_click' => 1.5,
            'lead_value_per_success' => 25.0,
            'retention_days' => 30,
        ],
    ];

    $request = new WP_REST_Request([
        'eventName' => 'cta_click',
        'payload' => ['source' => 'affiliate'],
    ]);

    AnalyticsService::ingestEvent($request);
    $headers = xdebug_get_headers();

    $required = [
        'Cache-Control: no-store, must-revalidate, max-age=0',
        'Pragma: no-cache',
        'X-Content-Type-Options: nosniff',
        'X-Frame-Options: DENY',
    ];

    foreach ($required as $header) {
        assertTrue(in_array($header, $headers, true), 'Missing expected security header: ' . $header);
    }

    echo "✓ AnalyticsService::ingestEvent security headers contract\n";
}

function testAnalyticsServiceExportHeadersContract(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportHeaders');
    $method->setAccessible(true);

    $headers = $method->invoke(null);

    assertTrue(is_array($headers), 'buildExportHeaders should return array');
    assertTrue(in_array('Content-Type: text/csv; charset=utf-8', $headers, true), 'CSV export headers should include content type');

    $hasDisposition = false;
    foreach ($headers as $headerLine) {
        if (str_starts_with((string) $headerLine, 'Content-Disposition: attachment; filename="poradnik-kpi-export-')) {
            $hasDisposition = true;
            break;
        }
    }
    assertTrue($hasDisposition, 'CSV export headers should include attachment filename');

    echo "✓ AnalyticsService::buildExportHeaders contract\n";
}

function testAnalyticsServiceExportCsvColumnsAndSortOrder(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportCsv');
    $method->setAccessible(true);

    $store = [
        '2026-03-20' => [
            'events' => ['a' => 2, 'b' => 1],
            'sources' => ['affiliate' => 3, 'organic' => 1],
            'revenue' => [
                'lead_success' => 1,
                'affiliate_clicks' => 2,
                'estimated_lead_revenue' => 30.0,
                'estimated_affiliate_revenue' => 5.0,
            ],
        ],
        '2026-03-18' => [
            'events' => ['x' => 1],
            'sources' => ['organic' => 1],
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
        ],
    ];

    $csv = (string) $method->invoke(null, $store);
    $lines = preg_split('/\r?\n/', trim($csv)) ?: [];

    assertTrue(count($lines) >= 3, 'CSV export should contain header and at least two data rows');

    $header = str_getcsv($lines[0]);
    assertSame([
        'day',
        'lead_success',
        'affiliate_clicks',
        'estimated_lead_revenue',
        'estimated_affiliate_revenue',
        'total_events',
        'top_source',
        'top_source_events',
    ], $header, 'CSV export should contain expected columns in order');

    $firstData = str_getcsv($lines[1]);
    $secondData = str_getcsv($lines[2]);

    assertSame('2026-03-18', $firstData[0] ?? null, 'CSV export should sort rows ascending by day');
    assertSame('2026-03-20', $secondData[0] ?? null, 'CSV export should sort rows ascending by day');

    echo "✓ AnalyticsService::buildExportCsv columns and day sort contract\n";
}

try {
    echo "Service unit tests\n\n";
    testPruneStoreRemovesOldDays();
    testPruneStoreRetentionOneKeepsOnlyToday();
    testLeadServiceSanitizesPayloadBeforeApiCall();
    testLeadServiceHoneypotShortCircuitsApiCall();
    testLeadServiceHandlesApiFailure();
    testAnalyticsServiceIngestEventRevenueMath();
    testAnalyticsServiceIngestEventHandlesInvalidPayload();
    testAnalyticsServiceRegistersPermissionCallback();
    testAnalyticsServiceSetsSecurityHeadersContract();
    testAnalyticsServiceExportHeadersContract();
    testAnalyticsServiceExportCsvColumnsAndSortOrder();

    echo "\nOverall: PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n");
    exit(1);
}
