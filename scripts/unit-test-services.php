#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lightweight unit tests for service classes without PHPUnit.
 *
 * Usage:
 *   php scripts/unit-test-services.php
 */

$capturedRemotePost = null;

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
        $capturedRemotePost = [
            'url' => $url,
            'args' => $args,
        ];

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

try {
    echo "Service unit tests\n\n";
    testPruneStoreRemovesOldDays();
    testLeadServiceSanitizesPayloadBeforeApiCall();
    testLeadServiceHoneypotShortCircuitsApiCall();

    echo "\nOverall: PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n");
    exit(1);
}
