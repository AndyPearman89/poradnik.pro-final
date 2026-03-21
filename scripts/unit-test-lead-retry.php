#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract tests for LeadService retry/backoff behavior.
 *
 * Usage:
 *   php scripts/unit-test-lead-retry.php
 */

$capturedRemotePosts = [];
$mockRemotePostResponse = null;
$mockRemotePostResponsesQueue = [];

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

if (! function_exists('rest_url')) {
    function rest_url(string $path): string
    {
        return 'http://localhost:8080/wp-json/' . ltrim($path, '/');
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        return 'test-nonce';
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array
    {
        global $capturedRemotePosts;
        global $mockRemotePostResponse;
        global $mockRemotePostResponsesQueue;

        $capturedRemotePosts[] = [
            'url' => $url,
            'args' => $args,
        ];

        if (is_array($mockRemotePostResponsesQueue) && $mockRemotePostResponsesQueue !== []) {
            $next = array_shift($mockRemotePostResponsesQueue);
            if (is_array($next)) {
                return $next;
            }
        }

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

require_once __DIR__ . '/../poradnik.pro/inc/ApiClient.php';
require_once __DIR__ . '/../poradnik.pro/inc/LeadService.php';

use PoradnikPro\LeadService;

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

function resetLeadRetryTestState(): void
{
    global $capturedRemotePosts;
    global $mockRemotePostResponse;
    global $mockRemotePostResponsesQueue;

    $capturedRemotePosts = [];
    $mockRemotePostResponse = null;
    $mockRemotePostResponsesQueue = [];
}

function testLeadServiceRetriesAndRecoversAfterTransientFailure(): void
{
    global $capturedRemotePosts;
    global $mockRemotePostResponsesQueue;

    resetLeadRetryTestState();

    $mockRemotePostResponsesQueue = [
        [
            'response' => ['code' => 500],
            'body' => '{"ok":false}',
        ],
        [
            'response' => ['code' => 201],
            'body' => '{"ok":true}',
        ],
    ];

    $response = LeadService::submit([
        'website' => '',
        'name' => 'Retry User',
        'email_or_phone' => 'retry@example.com',
        'problem' => 'Retry scenario',
        'location' => 'Wroclaw',
    ]);

    assertSame(true, (bool) ($response['ok'] ?? false), 'LeadService should recover when retry attempt succeeds');
    assertSame(201, (int) ($response['status'] ?? 0), 'LeadService should expose status from successful retry attempt');
    assertSame(2, count($capturedRemotePosts), 'LeadService should perform two attempts for one transient failure');
    assertSame(2, (int) ($response['attempts'] ?? 0), 'LeadService should expose attempt count when retry succeeds');

    echo "✓ LeadService::submit retry/backoff recovery contract\n";
}

function testLeadServiceStopsAfterMaxRetries(): void
{
    global $capturedRemotePosts;
    global $mockRemotePostResponsesQueue;

    resetLeadRetryTestState();

    $mockRemotePostResponsesQueue = [
        ['response' => ['code' => 503], 'body' => '{"ok":false}'],
        ['response' => ['code' => 503], 'body' => '{"ok":false}'],
        ['response' => ['code' => 503], 'body' => '{"ok":false}'],
    ];

    $response = LeadService::submit([
        'website' => '',
        'name' => 'Fail User',
        'email_or_phone' => 'fail@example.com',
        'problem' => 'Persistent outage',
        'location' => 'Gdansk',
    ]);

    assertSame(false, (bool) ($response['ok'] ?? true), 'LeadService should fail when all retries are exhausted');
    assertSame(503, (int) ($response['status'] ?? 0), 'LeadService should return final transient status after retries');
    assertSame(3, count($capturedRemotePosts), 'LeadService should perform max attempts for persistent transient failures');
    assertSame(3, (int) ($response['attempts'] ?? 0), 'LeadService should expose total attempts for persistent failure');

    echo "✓ LeadService::submit max retry exhaustion contract\n";
}

function testLeadServiceDoesNotRetryClientErrors(): void
{
    global $capturedRemotePosts;
    global $mockRemotePostResponsesQueue;

    resetLeadRetryTestState();

    $mockRemotePostResponsesQueue = [
        ['response' => ['code' => 422], 'body' => '{"ok":false}'],
    ];

    $response = LeadService::submit([
        'website' => '',
        'name' => 'Client Error',
        'email_or_phone' => 'client@example.com',
        'problem' => 'Validation fail',
        'location' => 'Lodz',
    ]);

    assertSame(false, (bool) ($response['ok'] ?? true), 'LeadService should return failure for client errors');
    assertSame(422, (int) ($response['status'] ?? 0), 'LeadService should expose client error status');
    assertSame(1, count($capturedRemotePosts), 'LeadService should not retry non-transient client errors');
    assertSame(1, (int) ($response['attempts'] ?? 0), 'LeadService should report one attempt for client errors');

    echo "✓ LeadService::submit no-retry contract for client errors\n";
}

try {
    echo "Lead retry unit tests\n\n";

    testLeadServiceRetriesAndRecoversAfterTransientFailure();
    testLeadServiceStopsAfterMaxRetries();
    testLeadServiceDoesNotRetryClientErrors();

    echo "\nOverall: PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n");
    exit(1);
}
