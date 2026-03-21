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
$mockCanManageOptions = true;
$mockNonceVerification = true;
$mockPostMetaByPostId = [];
$mockCurrentPostId = 0;

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

if (! function_exists('get_the_ID')) {
    function get_the_ID(): int
    {
        global $mockCurrentPostId;
        return (int) $mockCurrentPostId;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key, bool $single = false): mixed
    {
        global $mockPostMetaByPostId;

        if (! isset($mockPostMetaByPostId[$postId]) || ! array_key_exists($key, $mockPostMetaByPostId[$postId])) {
            return $single ? '' : [];
        }

        return $mockPostMetaByPostId[$postId][$key];
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

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        global $mockCanManageOptions;
        return (bool) $mockCanManageOptions;
    }
}

if (! function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action): bool
    {
        global $mockNonceVerification;
        return (bool) $mockNonceVerification;
    }
}

if (! function_exists('nocache_headers')) {
    function nocache_headers(): void
    {
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
require_once __DIR__ . '/../poradnik.pro/inc/MonetizationService.php';

use PoradnikPro\AnalyticsService;
use PoradnikPro\LeadService;
use PoradnikPro\MonetizationService;

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

function testPruneStoreRetentionBoundaryFourteenDays(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'pruneStore');
    $method->setAccessible(true);

    $today = gmdate('Y-m-d');
    $keepEdge = gmdate('Y-m-d', strtotime('-13 days UTC'));
    $dropEdge = gmdate('Y-m-d', strtotime('-14 days UTC'));

    $store = [
        $dropEdge => ['events' => ['old' => 1]],
        $keepEdge => ['events' => ['edge' => 1]],
        $today => ['events' => ['new' => 1]],
    ];

    $result = $method->invoke(null, $store, 14);

    assertTrue(isset($result[$keepEdge]), 'retention_days=14 should keep day at cutoff-1 (today-13)');
    assertTrue(! isset($result[$dropEdge]), 'retention_days=14 should drop day outside window (today-14)');
    assertTrue(isset($result[$today]), 'retention_days=14 should keep today');

    echo "✓ AnalyticsService::pruneStore retention_days=14 boundary regression\n";
}

function testPruneStoreRetentionBoundaryThreeHundredSixtyFiveDays(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'pruneStore');
    $method->setAccessible(true);

    $today = gmdate('Y-m-d');
    $keepEdge = gmdate('Y-m-d', strtotime('-364 days UTC'));
    $dropEdge = gmdate('Y-m-d', strtotime('-365 days UTC'));

    $store = [
        $dropEdge => ['events' => ['old' => 1]],
        $keepEdge => ['events' => ['edge' => 1]],
        $today => ['events' => ['new' => 1]],
    ];

    $result = $method->invoke(null, $store, 365);

    assertTrue(isset($result[$keepEdge]), 'retention_days=365 should keep day at cutoff-1 (today-364)');
    assertTrue(! isset($result[$dropEdge]), 'retention_days=365 should drop day outside window (today-365)');
    assertTrue(isset($result[$today]), 'retention_days=365 should keep today');

    echo "✓ AnalyticsService::pruneStore retention_days=365 boundary regression\n";
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
    assertSame(1, (int) ($store[$day]['quality']['invalid_payload_count'] ?? 0), 'ingestEvent should increment invalid payload count for unknown payload');

    echo "✓ AnalyticsService::ingestEvent invalid payload resilience\n";
}

function testAnalyticsServiceIngestEventUnknownEventAllowlistFallback(): void
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

    $response = AnalyticsService::ingestEvent(new WP_REST_Request([
        'eventName' => 'custom_non_allowlisted_event',
        'payload' => [
            'source' => 'organic',
        ],
    ]));

    assertSame(202, $response->get_status(), 'ingestEvent should accept non-allowlisted event via fallback');
    assertSame('unknown', (string) ($response->get_data()['event'] ?? ''), 'non-allowlisted event should normalize to unknown');

    $day = gmdate('Y-m-d');
    $store = (array) ($mockOptions['poradnik_pro_kpi_store'] ?? []);
    assertSame(1, (int) ($store[$day]['events']['unknown'] ?? 0), 'unknown fallback should increment unknown event bucket');

    echo "✓ AnalyticsService::ingestEvent event allowlist fallback contract\n";
}

function testAnalyticsServiceIngestEventPayloadAllowlistContract(): void
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

    $response = AnalyticsService::ingestEvent(new WP_REST_Request([
        'eventName' => 'cta_click',
        'payload' => [
            'channel' => 'YouTube Shorts',
            'source' => '  ' ,
            'forbidden' => 'drop-me',
            'nested' => ['x' => 1],
        ],
    ]));

    assertSame(202, $response->get_status(), 'ingestEvent should accept payload with extra keys');

    $day = gmdate('Y-m-d');
    $store = (array) ($mockOptions['poradnik_pro_kpi_store'] ?? []);

    assertSame(1, (int) ($store[$day]['events']['cta_click'] ?? 0), 'allowlisted event should be counted as-is');
    assertSame(1, (int) ($store[$day]['sources']['youtubeshorts'] ?? 0), 'source should fallback to normalized allowlisted channel value when source is blank');
    assertSame(0, (int) ($store[$day]['sources']['drop-me'] ?? 0), 'non-allowlisted payload keys should not become tracking sources');

    echo "✓ AnalyticsService::ingestEvent payload allowlist contract\n";
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

function testAnalyticsServiceExportCsvValueContract(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportCsv');
    $method->setAccessible(true);

    $store = [
        '2026-03-21' => [
            'events' => ['a' => 2, 'b' => 3],
            'sources' => [],
            'revenue' => [
                'lead_success' => 1,
                'affiliate_clicks' => 2,
                'estimated_lead_revenue' => 1.2,
                'estimated_affiliate_revenue' => 3,
            ],
        ],
    ];

    $csv = (string) $method->invoke(null, $store);
    $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
    assertTrue(count($lines) >= 2, 'CSV value contract should include at least one data row');

    $row = str_getcsv($lines[1]);

    assertSame('2026-03-21', $row[0] ?? null, 'CSV row should contain correct day');
    assertSame('1', $row[1] ?? null, 'CSV row should contain lead_success');
    assertSame('2', $row[2] ?? null, 'CSV row should contain affiliate_clicks');
    assertSame('1.20', $row[3] ?? null, 'CSV lead revenue should be formatted to two decimals');
    assertSame('3.00', $row[4] ?? null, 'CSV affiliate revenue should be formatted to two decimals');
    assertSame('5', $row[5] ?? null, 'CSV total_events should be sum of events');
    assertSame('unknown', $row[6] ?? null, 'CSV top_source should fallback to unknown when sources are empty');
    assertSame('0', $row[7] ?? null, 'CSV top_source_events should fallback to 0 when sources are empty');

    echo "✓ AnalyticsService::buildExportCsv value contract\n";
}

function testAnalyticsServiceExportCsvFull365DaysContract(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportCsv');
    $method->setAccessible(true);

    $startDate = new DateTimeImmutable('2025-01-01 00:00:00 UTC');
    $store = [];

    for ($i = 0; $i < 365; $i++) {
        $day = $startDate->modify('+' . $i . ' days')->format('Y-m-d');
        $store[$day] = [
            'events' => [
                'evt' => $i + 1,
            ],
            'sources' => [
                'source_' . (($i % 3) + 1) => $i + 2,
            ],
            'revenue' => [
                'lead_success' => $i % 5,
                'affiliate_clicks' => $i % 7,
                'estimated_lead_revenue' => (float) ($i % 5) * 10,
                'estimated_affiliate_revenue' => (float) ($i % 7) * 1.5,
            ],
        ];
    }

    $csv = (string) $method->invoke(null, $store);
    $lines = preg_split('/\r?\n/', trim($csv)) ?: [];

    assertSame(366, count($lines), '365-day export should produce 1 header + 365 data rows');

    $firstData = str_getcsv($lines[1]);
    $lastData = str_getcsv($lines[365]);

    assertSame('2025-01-01', $firstData[0] ?? null, '365-day export should keep earliest day in first data row');
    assertSame('2025-12-31', $lastData[0] ?? null, '365-day export should keep latest day in last data row');

    echo "✓ AnalyticsService::buildExportCsv full 365-day contract\n";
}

function testAnalyticsServiceConfigRetentionClamp(): void
{
    global $mockCanManageOptions;
    global $mockNonceVerification;
    global $mockOptions;

    $mockCanManageOptions = true;
    $mockNonceVerification = true;
    $mockOptions = [];

    $method = new ReflectionMethod(AnalyticsService::class, 'handleConfigPost');
    $method->setAccessible(true);

    $_POST = [
        'poradnik_pro_kpi_save' => '1',
        'poradnik_pro_kpi_nonce' => 'ok',
        'affiliate_value_per_click' => '1.5',
        'lead_value_per_success' => '25',
        'retention_days' => '1',
    ];
    $method->invoke(null);

    $cfg = $mockOptions['poradnik_pro_kpi_config'] ?? [];
    assertSame(14, (int) ($cfg['retention_days'] ?? 0), 'retention_days should clamp to minimum 14');

    $_POST['retention_days'] = '999';
    $method->invoke(null);

    $cfg = $mockOptions['poradnik_pro_kpi_config'] ?? [];
    assertSame(365, (int) ($cfg['retention_days'] ?? 0), 'retention_days should clamp to maximum 365');

    $_POST = [];

    echo "✓ AnalyticsService::handleConfigPost retention clamp regression\n";
}

function testAnalyticsServiceExportNonceFlowInvalidNonceReturnsEarly(): void
{
    global $mockCanManageOptions;
    global $mockNonceVerification;

    $mockCanManageOptions = true;
    $mockNonceVerification = false;

    $method = new ReflectionMethod(AnalyticsService::class, 'handleExportRequest');
    $method->setAccessible(true);

    $_GET = [
        'poradnik_pro_export' => 'csv',
        '_wpnonce' => 'invalid',
    ];

    $beforeHeaders = function_exists('xdebug_get_headers') ? xdebug_get_headers() : [];
    $beforeLen = strlen((string) ob_get_contents());

    $method->invoke(null);

    $afterLen = strlen((string) ob_get_contents());
    assertSame($beforeLen, $afterLen, 'invalid export nonce should not output CSV payload');

    if (function_exists('xdebug_get_headers')) {
        $afterHeaders = xdebug_get_headers();
        assertSame(count($beforeHeaders), count($afterHeaders), 'invalid export nonce should not add response headers');
    }

    $_GET = [];
    $mockNonceVerification = true;

    echo "✓ AnalyticsService::handleExportRequest nonce-flow regression\n";
}

function testAnalyticsServiceExportPayloadValidNonceContract(): void
{
    global $mockCanManageOptions;
    global $mockNonceVerification;
    global $mockOptions;

    $mockCanManageOptions = true;
    $mockNonceVerification = true;
    $mockOptions = [
        'poradnik_pro_kpi_store' => [
            '2026-03-20' => [
                'events' => ['a' => 1],
                'sources' => ['affiliate' => 1],
                'revenue' => [
                    'lead_success' => 1,
                    'affiliate_clicks' => 2,
                    'estimated_lead_revenue' => 30.0,
                    'estimated_affiliate_revenue' => 3.0,
                ],
            ],
            '2026-03-19' => [
                'events' => ['b' => 2],
                'sources' => ['organic' => 2],
                'revenue' => [
                    'lead_success' => 0,
                    'affiliate_clicks' => 0,
                    'estimated_lead_revenue' => 0.0,
                    'estimated_affiliate_revenue' => 0.0,
                ],
            ],
        ],
    ];

    $_GET = [
        'poradnik_pro_export' => 'csv',
        '_wpnonce' => 'valid',
    ];

    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportPayloadFromRequest');
    $method->setAccessible(true);
    $payload = $method->invoke(null);

    assertTrue(is_array($payload), 'buildExportPayloadFromRequest should return payload for valid nonce flow');

    $headers = (array) ($payload['headers'] ?? []);
    $hasDisposition = false;
    foreach ($headers as $headerLine) {
        if (str_starts_with((string) $headerLine, 'Content-Disposition: attachment; filename="poradnik-kpi-export-')) {
            $hasDisposition = true;
            break;
        }
    }
    assertTrue($hasDisposition, 'valid export payload should include Content-Disposition header');

    $csv = (string) ($payload['csv'] ?? '');
    $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
    assertTrue(count($lines) >= 3, 'valid export payload should include header + data rows');

    $row1 = str_getcsv($lines[1]);
    $row2 = str_getcsv($lines[2]);
    assertSame('2026-03-19', $row1[0] ?? null, 'valid export payload should keep ascending day sorting');
    assertSame('2026-03-20', $row2[0] ?? null, 'valid export payload should keep ascending day sorting');

    $_GET = [];

    echo "✓ AnalyticsService::buildExportPayloadFromRequest valid nonce contract\n";
}

function testAnalyticsServiceExportPayloadDeniedWithoutManageOptions(): void
{
    global $mockCanManageOptions;
    global $mockNonceVerification;

    $mockCanManageOptions = false;
    $mockNonceVerification = true;

    $_GET = [
        'poradnik_pro_export' => 'csv',
        '_wpnonce' => 'valid',
    ];

    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportPayloadFromRequest');
    $method->setAccessible(true);
    $payload = $method->invoke(null);

    assertSame(null, $payload, 'buildExportPayloadFromRequest should return null without manage_options capability');

    $_GET = [];
    $mockCanManageOptions = true;

    echo "✓ AnalyticsService::buildExportPayloadFromRequest manage_options guard\n";
}

function testAnalyticsServiceExportPayloadRequiresCsvParam(): void
{
    global $mockCanManageOptions;
    global $mockNonceVerification;

    $mockCanManageOptions = true;
    $mockNonceVerification = true;

    $_GET = [
        // missing poradnik_pro_export=csv
        '_wpnonce' => 'valid',
    ];

    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportPayloadFromRequest');
    $method->setAccessible(true);
    $payload = $method->invoke(null);

    assertSame(null, $payload, 'buildExportPayloadFromRequest should return null when export=csv parameter is missing');

    $_GET = [];

    echo "✓ AnalyticsService::buildExportPayloadFromRequest export param guard\n";
}

function testAnalyticsServiceExportCsvEmptyStoreHeaderOnly(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportCsv');
    $method->setAccessible(true);

    $csv = (string) $method->invoke(null, []);
    $lines = preg_split('/\r?\n/', trim($csv)) ?: [];

    assertSame(1, count($lines), 'empty store export should contain only header row');

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
    ], $header, 'empty store export should keep expected header columns');

    echo "✓ AnalyticsService::buildExportCsv empty store header-only contract\n";
}

function testAnalyticsServiceExportFilenameTimestampSmokeCheck(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildExportHeaders');
    $method->setAccessible(true);

    $headers = (array) $method->invoke(null);
    $disposition = '';

    foreach ($headers as $headerLine) {
        $line = (string) $headerLine;
        if (str_starts_with($line, 'Content-Disposition:')) {
            $disposition = $line;
            break;
        }
    }

    assertTrue($disposition !== '', 'export headers should include Content-Disposition');
    $matches = preg_match('/filename="poradnik-kpi-export-\d{8}-\d{6}\.csv"$/', $disposition) === 1;
    assertTrue($matches, 'export filename should include timestamp format Ymd-His');

    echo "✓ AnalyticsService::buildExportHeaders filename timestamp smoke-check\n";
}

function testAnalyticsServiceBuildSummaryMultiDayAggregation(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $rows = [
        '2026-03-20' => [
            'revenue' => [
                'lead_success' => 2,
                'affiliate_clicks' => 3,
                'estimated_lead_revenue' => 60.0,
                'estimated_affiliate_revenue' => 4.5,
            ],
            'quality' => [
                'invalid_payload_count' => 1,
            ],
            'sources' => [
                'organic' => 5,
                'affiliate' => 2,
            ],
        ],
        '2026-03-19' => [
            'revenue' => [
                'lead_success' => 1,
                'affiliate_clicks' => 4,
                'estimated_lead_revenue' => 30.0,
                'estimated_affiliate_revenue' => 6.0,
            ],
            'quality' => [
                'invalid_payload_count' => 2,
            ],
            'sources' => [
                'affiliate' => 7,
                'social' => 1,
            ],
        ],
    ];

    $summary = (array) $method->invoke(null, $rows);

    assertSame(3, (int) ($summary['lead_success'] ?? 0), 'buildSummary should aggregate lead_success across multiple days');
    assertSame(7, (int) ($summary['affiliate_clicks'] ?? 0), 'buildSummary should aggregate affiliate_clicks across multiple days');
    assertSame(3, (int) ($summary['invalid_payload_count'] ?? 0), 'buildSummary should aggregate invalid_payload_count across multiple days');
    assertSame(100.5, (float) ($summary['estimated_total_revenue'] ?? 0), 'buildSummary should sum lead and affiliate revenues across multiple days');

    $topSources = (array) ($summary['top_sources'] ?? []);
    assertSame(
        [
            'affiliate' => 9,
            'organic' => 5,
            'social' => 1,
        ],
        $topSources,
        'buildSummary should aggregate top_sources and sort descending by total count'
    );

    echo "✓ AnalyticsService::buildSummary multi-day aggregation contract\n";
}

function testAnalyticsServiceBuildSummaryEmptyInputFallback(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $summary = (array) $method->invoke(null, []);

    assertSame(0, (int) ($summary['lead_success'] ?? -1), 'buildSummary should return lead_success=0 for empty input');
    assertSame(0, (int) ($summary['affiliate_clicks'] ?? -1), 'buildSummary should return affiliate_clicks=0 for empty input');
    assertSame(0, (int) ($summary['invalid_payload_count'] ?? -1), 'buildSummary should return invalid_payload_count=0 for empty input');
    assertSame(0.0, (float) ($summary['estimated_total_revenue'] ?? -1), 'buildSummary should return estimated_total_revenue=0.0 for empty input');
    assertSame([], (array) ($summary['top_sources'] ?? ['unexpected']), 'buildSummary should return empty top_sources for empty input');

    echo "✓ AnalyticsService::buildSummary empty-input fallback contract\n";
}

function testAnalyticsServiceBuildSummaryTopSourcesLimit(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $sources = [];
    for ($i = 1; $i <= 12; $i++) {
        $sources['s' . $i] = $i;
    }

    $rows = [
        '2026-03-22' => [
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
            'sources' => $sources,
        ],
    ];

    $summary = (array) $method->invoke(null, $rows);
    $topSources = (array) ($summary['top_sources'] ?? []);

    assertSame(10, count($topSources), 'buildSummary should limit top_sources to 10 entries');
    assertTrue(isset($topSources['s12']), 'buildSummary should keep highest-count sources in top_sources');
    assertTrue(isset($topSources['s3']), 'buildSummary should include tenth source when 12 sources exist');
    assertTrue(! isset($topSources['s2']), 'buildSummary should drop sources below top 10 threshold');
    assertTrue(! isset($topSources['s1']), 'buildSummary should drop sources below top 10 threshold');

    $keys = array_keys($topSources);
    assertSame('s12', (string) ($keys[0] ?? ''), 'buildSummary should keep descending source order at the top');
    assertSame('s3', (string) ($keys[9] ?? ''), 'buildSummary should keep descending source order at the tenth position');

    echo "✓ AnalyticsService::buildSummary top_sources limit contract\n";
}

function testAnalyticsServiceBuildSummaryMissingKeysFallback(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $rows = [
        '2026-03-23' => [
            // Missing revenue and sources on purpose to verify defensive defaults.
            'events' => ['x' => 1],
        ],
        '2026-03-22' => [
            'revenue' => [
                'lead_success' => 2,
                'affiliate_clicks' => 1,
                'estimated_lead_revenue' => 50.0,
                'estimated_affiliate_revenue' => 2.0,
            ],
            'sources' => [
                'organic' => 3,
            ],
        ],
    ];

    $summary = (array) $method->invoke(null, $rows);

    assertSame(2, (int) ($summary['lead_success'] ?? -1), 'buildSummary should fallback missing revenue keys to zero and aggregate valid rows');
    assertSame(1, (int) ($summary['affiliate_clicks'] ?? -1), 'buildSummary should keep valid affiliate_clicks when one row has missing keys');
    assertSame(52.0, (float) ($summary['estimated_total_revenue'] ?? -1), 'buildSummary should calculate revenue using available row data only');
    assertSame(['organic' => 3], (array) ($summary['top_sources'] ?? []), 'buildSummary should fallback missing sources to empty array without crashing');

    echo "✓ AnalyticsService::buildSummary missing-keys fallback contract\n";
}

function testAnalyticsServiceBuildSummarySourceTypeNormalization(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $rows = [
        '2026-03-24' => [
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
            'sources' => [
                'organic' => '5',
                'affiliate' => null,
                'social' => 'oops',
                'paid' => -3,
            ],
        ],
        '2026-03-23' => [
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
            'sources' => [
                'organic' => '2.9',
                'affiliate' => '3',
            ],
        ],
    ];

    $summary = (array) $method->invoke(null, $rows);
    $topSources = (array) ($summary['top_sources'] ?? []);

    assertSame(7, (int) ($topSources['organic'] ?? -1), 'buildSummary should normalize numeric strings and aggregate source counts');
    assertSame(3, (int) ($topSources['affiliate'] ?? -1), 'buildSummary should ignore null and keep valid numeric source counts');
    assertSame(0, (int) ($topSources['social'] ?? -1), 'buildSummary should normalize non-numeric source count to 0');
    assertSame(0, (int) ($topSources['paid'] ?? -1), 'buildSummary should clamp negative source count to 0');

    echo "✓ AnalyticsService::buildSummary source-type normalization contract\n";
}

function testAnalyticsServiceBuildSummaryTopSourcesTieDeterminism(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $rows = [
        '2026-03-25' => [
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
            'sources' => [
                'kappa' => 5,
                'alpha' => 5,
                'mu' => 5,
                'beta' => 5,
                'lambda' => 5,
                'gamma' => 5,
                'eta' => 5,
                'theta' => 5,
                'zeta' => 5,
                'delta' => 5,
                'epsilon' => 5,
                'iota' => 5,
            ],
        ],
    ];

    $summary = (array) $method->invoke(null, $rows);
    $topSources = (array) ($summary['top_sources'] ?? []);

    assertSame(10, count($topSources), 'buildSummary should keep 10 sources when tie-count set exceeds limit');

    $expectedKeys = [
        'alpha',
        'beta',
        'delta',
        'epsilon',
        'eta',
        'gamma',
        'iota',
        'kappa',
        'lambda',
        'mu',
    ];
    assertSame($expectedKeys, array_keys($topSources), 'buildSummary should use deterministic alphabetical order for tie counts');

    echo "✓ AnalyticsService::buildSummary tie-order determinism contract\n";
}

function testAnalyticsServiceTrackEndpointTopSourcesTieMultiDayIntegration(): void
{
    global $mockOptions;

    $today = gmdate('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime('-1 day UTC'));

    // Seed previous day so endpoint ingest on current day forms a multi-day tie.
    $mockOptions = [
        'poradnik_pro_kpi_store' => [
            $yesterday => [
                'events' => [
                    'cta_click' => 2,
                ],
                'sources' => [
                    'alpha' => 1,
                    'beta' => 1,
                ],
                'revenue' => [
                    'affiliate_clicks' => 0,
                    'lead_success' => 0,
                    'estimated_affiliate_revenue' => 0.0,
                    'estimated_lead_revenue' => 0.0,
                ],
            ],
        ],
        'poradnik_pro_kpi_config' => [
            'affiliate_value_per_click' => 1.5,
            'lead_value_per_success' => 25.0,
            'retention_days' => 30,
        ],
    ];

    AnalyticsService::ingestEvent(new WP_REST_Request([
        'eventName' => 'cta_click',
        'payload' => ['source' => 'beta'],
    ]));
    AnalyticsService::ingestEvent(new WP_REST_Request([
        'eventName' => 'cta_click',
        'payload' => ['source' => 'alpha'],
    ]));

    $store = (array) ($mockOptions['poradnik_pro_kpi_store'] ?? []);
    $rows = [];
    if (isset($store[$today])) {
        $rows[$today] = $store[$today];
    }
    if (isset($store[$yesterday])) {
        $rows[$yesterday] = $store[$yesterday];
    }

    $summaryMethod = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $summaryMethod->setAccessible(true);

    $summary = (array) $summaryMethod->invoke(null, $rows);
    $topSources = (array) ($summary['top_sources'] ?? []);

    assertSame(2, (int) ($topSources['alpha'] ?? -1), '/track integration should aggregate alpha count across days');
    assertSame(2, (int) ($topSources['beta'] ?? -1), '/track integration should aggregate beta count across days');
    assertSame(
        ['alpha', 'beta'],
        array_slice(array_keys($topSources), 0, 2),
        '/track integration should keep deterministic alphabetical order for tie counts across days'
    );

    echo "✓ AnalyticsService::ingestEvent + buildSummary multi-day tie-order integration\n";
}

function testAnalyticsServiceTrackEndpointInvalidPayloadCountIntegration(): void
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

    AnalyticsService::ingestEvent(new WP_REST_Request([
        'eventName' => 'cta_click',
        'payload' => [
            'source' => 'affiliate',
        ],
    ]));

    AnalyticsService::ingestEvent(new WP_REST_Request([
        'eventName' => 'not_allowed_event',
        'payload' => [
            'source' => 'unknown',
        ],
    ]));

    $day = gmdate('Y-m-d');
    $store = (array) ($mockOptions['poradnik_pro_kpi_store'] ?? []);
    $rows = isset($store[$day]) ? [$day => $store[$day]] : [];

    $summaryMethod = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $summaryMethod->setAccessible(true);
    $summary = (array) $summaryMethod->invoke(null, $rows);

    assertSame(1, (int) ($summary['invalid_payload_count'] ?? 0), 'invalid payload counter should increase for non-allowlisted event payload');

    echo "✓ AnalyticsService::ingestEvent invalid payload KPI integration\n";
}

function testAnalyticsServiceBuildSummaryExperimentReportContract(): void
{
    $method = new ReflectionMethod(AnalyticsService::class, 'buildSummary');
    $method->setAccessible(true);

    $rows = [
        '2026-03-25' => [
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
            'experiments' => [
                'conversion_hero_v1' => [
                    'A' => [
                        'cta_clicks' => 10,
                        'lead_success' => 2,
                    ],
                    'B' => [
                        'cta_clicks' => 5,
                        'lead_success' => 2,
                    ],
                ],
            ],
        ],
        '2026-03-24' => [
            'revenue' => [
                'lead_success' => 0,
                'affiliate_clicks' => 0,
                'estimated_lead_revenue' => 0.0,
                'estimated_affiliate_revenue' => 0.0,
            ],
            'experiments' => [
                'conversion_hero_v1' => [
                    'A' => [
                        'cta_clicks' => 5,
                        'lead_success' => 1,
                    ],
                ],
            ],
        ],
    ];

    $summary = (array) $method->invoke(null, $rows);
    $report = (array) ($summary['experiment_report'] ?? []);

    assertSame(2, count($report), 'buildSummary should expose two rows for A/B experiment report');

    $rowA = $report[0] ?? [];
    $rowB = $report[1] ?? [];

    assertSame('conversion_hero_v1', (string) ($rowA['experiment'] ?? ''), 'experiment report should keep experiment key');
    assertSame('A', (string) ($rowA['variant'] ?? ''), 'experiment report should sort variants alphabetically (A first)');
    assertSame(15, (int) ($rowA['cta_clicks'] ?? 0), 'experiment report should aggregate CTA clicks across days for variant A');
    assertSame(3, (int) ($rowA['lead_success'] ?? 0), 'experiment report should aggregate lead success across days for variant A');
    assertSame(20.0, round((float) ($rowA['conversion_rate'] ?? 0), 2), 'experiment report should calculate conversion rate for variant A');

    assertSame('B', (string) ($rowB['variant'] ?? ''), 'experiment report should include variant B');
    assertSame(5, (int) ($rowB['cta_clicks'] ?? 0), 'experiment report should keep CTA clicks for variant B');
    assertSame(2, (int) ($rowB['lead_success'] ?? 0), 'experiment report should keep lead success for variant B');
    assertSame(40.0, round((float) ($rowB['conversion_rate'] ?? 0), 2), 'experiment report should calculate conversion rate for variant B');

    echo "✓ AnalyticsService::buildSummary experiment report contract\n";
}

function testMonetizationServicePremiumWeightingTopThreeDeterminism(): void
{
    global $mockPostMetaByPostId;

    $postId = 9901;
    $mockPostMetaByPostId = [
        $postId => [
            'pp_offers_json' => json_encode([
                [
                    'name' => 'Offer Low',
                    'rating' => 4.0,
                    'epc' => 1.0,
                    'premium' => false,
                ],
                [
                    'name' => 'Offer Premium A',
                    'rating' => 4.6,
                    'epc' => 3.2,
                    'premium' => true,
                ],
                [
                    'name' => 'Offer Premium B',
                    'rating' => 4.5,
                    'epc' => 3.1,
                    'premium' => true,
                ],
                [
                    'name' => 'Offer NonPremium Mid',
                    'rating' => 4.4,
                    'epc' => 3.0,
                    'premium' => false,
                ],
                [
                    'name' => 'Offer Premium C',
                    'rating' => 4.3,
                    'epc' => 3.3,
                    'premium' => true,
                ],
            ], JSON_UNESCAPED_UNICODE),
        ],
    ];

    $offers = MonetizationService::rankedOffers($postId);

    assertSame(5, count($offers), 'rankedOffers should return all valid offers');
    assertSame(1, (int) ($offers[0]['rank'] ?? 0), 'top offer should have rank=1');
    assertSame(2, (int) ($offers[1]['rank'] ?? 0), 'second offer should have rank=2');
    assertSame(3, (int) ($offers[2]['rank'] ?? 0), 'third offer should have rank=3');
    assertSame('PREMIUM+', (string) ($offers[0]['badge'] ?? ''), 'top 1 should get PREMIUM+ badge');
    assertSame('PREMIUM+', (string) ($offers[1]['badge'] ?? ''), 'top 2 should get PREMIUM+ badge');
    assertSame('PREMIUM+', (string) ($offers[2]['badge'] ?? ''), 'top 3 should get PREMIUM+ badge');
    assertSame('PREMIUM', (string) ($offers[3]['badge'] ?? ''), 'offer outside top 3 should get PREMIUM badge');

    $topThreeNames = array_map(
        static fn (array $offer): string => (string) ($offer['name'] ?? ''),
        array_slice($offers, 0, 3)
    );
    assertSame(
        ['Offer Premium A', 'Offer Premium B', 'Offer Premium C'],
        $topThreeNames,
        'premium weighting should deterministically promote premium offers into top 3 for this fixture'
    );

    echo "✓ MonetizationService::rankedOffers premium weighting top-3 determinism\n";
}

function testMonetizationServiceTieBehaviorDeterministicByInputOrder(): void
{
    global $mockPostMetaByPostId;

    $postId = 9902;
    $mockPostMetaByPostId = [
        $postId => [
            'pp_offers_json' => json_encode([
                [
                    'name' => 'Tie Offer A',
                    'rating' => 4.5,
                    'epc' => 3.0,
                    'premium' => false,
                ],
                [
                    'name' => 'Tie Offer B',
                    'rating' => 4.5,
                    'epc' => 3.0,
                    'premium' => false,
                ],
                [
                    'name' => 'Tie Offer C',
                    'rating' => 4.5,
                    'epc' => 3.0,
                    'premium' => false,
                ],
                [
                    'name' => 'Tie Offer D',
                    'rating' => 4.5,
                    'epc' => 3.0,
                    'premium' => false,
                ],
            ], JSON_UNESCAPED_UNICODE),
        ],
    ];

    $offers = MonetizationService::rankedOffers($postId);

    $orderedNames = array_map(
        static fn (array $offer): string => (string) ($offer['name'] ?? ''),
        $offers
    );
    assertSame(
        ['Tie Offer A', 'Tie Offer B', 'Tie Offer C', 'Tie Offer D'],
        $orderedNames,
        'rankedOffers should keep deterministic input order when score/rating/epc/premium are tied'
    );

    $scores = array_map(static fn (array $offer): float => (float) ($offer['score'] ?? -1), $offers);
    assertTrue($scores[0] > $scores[1], 'position boost should still differentiate earlier tied offers from later ones');

    echo "✓ MonetizationService::rankedOffers tie behavior determinism\n";
}

function testMonetizationServiceResolveOfferCtaDirectUrlContract(): void
{
    $result = MonetizationService::resolveOfferCta([
        'affiliate_url' => 'https://partner.example.com/offer-1',
    ], '/uslugi/');

    assertSame('https://partner.example.com/offer-1', (string) ($result['url'] ?? ''), 'resolveOfferCta should keep valid direct affiliate URL');
    assertSame(false, (bool) ($result['is_fallback'] ?? true), 'resolveOfferCta should mark valid URL as non-fallback');

    echo "✓ MonetizationService::resolveOfferCta direct-url contract\n";
}

function testMonetizationServiceResolveOfferCtaFallbackContract(): void
{
    $fallbackUrl = '/uslugi/';

    $empty = MonetizationService::resolveOfferCta([
        'affiliate_url' => '',
    ], $fallbackUrl);

    $hash = MonetizationService::resolveOfferCta([
        'affiliate_url' => '#',
    ], $fallbackUrl);

    assertSame($fallbackUrl, (string) ($empty['url'] ?? ''), 'resolveOfferCta should fallback to lead URL when affiliate_url is empty');
    assertSame(true, (bool) ($empty['is_fallback'] ?? false), 'resolveOfferCta should mark empty affiliate URL as fallback');
    assertSame($fallbackUrl, (string) ($hash['url'] ?? ''), 'resolveOfferCta should fallback to lead URL when affiliate_url is hash placeholder');
    assertSame(true, (bool) ($hash['is_fallback'] ?? false), 'resolveOfferCta should mark hash affiliate URL as fallback');

    echo "✓ MonetizationService::resolveOfferCta affiliate->lead fallback contract\n";
}

function testAffiliateDisclosureTemplateContract(): void
{
    $templatePath = __DIR__ . '/../poradnik.pro/template-parts/components/affiliate-disclosure.php';
    $contents = (string) file_get_contents($templatePath);

    assertTrue($contents !== '', 'affiliate disclosure template should be readable');
    assertTrue(str_contains($contents, 'data-pp-affiliate-disclosure'), 'affiliate disclosure template should expose marker data-pp-affiliate-disclosure');
    assertTrue(str_contains($contents, 'Disclosure:'), 'affiliate disclosure template should keep disclosure text prefix');

    echo "✓ Affiliate disclosure template contract\n";
}

try {
    echo "Service unit tests\n\n";
    testPruneStoreRemovesOldDays();
    testPruneStoreRetentionOneKeepsOnlyToday();
    testPruneStoreRetentionBoundaryFourteenDays();
    testPruneStoreRetentionBoundaryThreeHundredSixtyFiveDays();
    testLeadServiceSanitizesPayloadBeforeApiCall();
    testLeadServiceHoneypotShortCircuitsApiCall();
    testLeadServiceHandlesApiFailure();
    testAnalyticsServiceIngestEventRevenueMath();
    testAnalyticsServiceIngestEventHandlesInvalidPayload();
    testAnalyticsServiceIngestEventUnknownEventAllowlistFallback();
    testAnalyticsServiceIngestEventPayloadAllowlistContract();
    testAnalyticsServiceRegistersPermissionCallback();
    testAnalyticsServiceSetsSecurityHeadersContract();
    testAnalyticsServiceExportHeadersContract();
    testAnalyticsServiceExportCsvColumnsAndSortOrder();
    testAnalyticsServiceConfigRetentionClamp();
    testAnalyticsServiceExportNonceFlowInvalidNonceReturnsEarly();
    testAnalyticsServiceExportPayloadValidNonceContract();
    testAnalyticsServiceExportPayloadDeniedWithoutManageOptions();
    testAnalyticsServiceExportPayloadRequiresCsvParam();
    testAnalyticsServiceExportCsvEmptyStoreHeaderOnly();
    testAnalyticsServiceExportFilenameTimestampSmokeCheck();
    testAnalyticsServiceExportCsvValueContract();
    testAnalyticsServiceExportCsvFull365DaysContract();
    testAnalyticsServiceBuildSummaryMultiDayAggregation();
    testAnalyticsServiceBuildSummaryEmptyInputFallback();
    testAnalyticsServiceBuildSummaryTopSourcesLimit();
    testAnalyticsServiceBuildSummaryMissingKeysFallback();
    testAnalyticsServiceBuildSummarySourceTypeNormalization();
    testAnalyticsServiceBuildSummaryTopSourcesTieDeterminism();
    testAnalyticsServiceTrackEndpointTopSourcesTieMultiDayIntegration();
    testAnalyticsServiceTrackEndpointInvalidPayloadCountIntegration();
    testAnalyticsServiceBuildSummaryExperimentReportContract();
    testMonetizationServicePremiumWeightingTopThreeDeterminism();
    testMonetizationServiceTieBehaviorDeterministicByInputOrder();
    testMonetizationServiceResolveOfferCtaDirectUrlContract();
    testMonetizationServiceResolveOfferCtaFallbackContract();
    testAffiliateDisclosureTemplateContract();

    echo "\nOverall: PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n");
    exit(1);
}
