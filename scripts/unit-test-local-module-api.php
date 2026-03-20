#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Lightweight contract tests for PearTreeLocalModuleApi without PHPUnit.
 *
 * Usage:
 *   php scripts/unit-test-local-module-api.php
 */

$mockOptions = [];
$mockTransients = [];
$uuidCounter = 0;

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $jsonParams;
        private array $params;
        private array $headers;

        public function __construct(array $jsonParams = [], array $params = [], array $headers = [])
        {
            $this->jsonParams = $jsonParams;
            $this->params = $params;
            $this->headers = $headers;
        }

        public function get_json_params(): array
        {
            return $this->jsonParams;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function get_header(string $key): string
        {
            $key = strtolower($key);
            foreach ($this->headers as $hKey => $value) {
                if (strtolower((string) $hKey) === $key) {
                    return (string) $value;
                }
            }

            return '';
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

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public function __construct(
            public int $ID,
            public string $post_excerpt = '',
            public string $post_content = ''
        ) {
        }
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(string|array $value): string|array
    {
        if (is_array($value)) {
            return array_map(static fn ($v) => is_string($v) ? stripslashes($v) : $v, $value);
        }

        return stripslashes($value);
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return strip_tags($text);
    }
}

if (! function_exists('wp_trim_words')) {
    function wp_trim_words(string $text, int $numWords = 55): string
    {
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        if (count($parts) <= $numWords) {
            return trim($text);
        }

        return implode(' ', array_slice($parts, 0, $numWords));
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

if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration): bool
    {
        global $mockTransients;
        $mockTransients[$key] = [
            'value' => $value,
            'expires_at' => time() + max(1, $expiration),
        ];
        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        global $mockTransients;

        if (! isset($mockTransients[$key])) {
            return false;
        }

        $item = $mockTransients[$key];
        if (($item['expires_at'] ?? 0) <= time()) {
            unset($mockTransients[$key]);
            return false;
        }

        return $item['value'] ?? false;
    }
}

if (! function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        global $uuidCounter;
        $uuidCounter++;
        return sprintf('00000000-0000-4000-8000-%012d', $uuidCounter);
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
    }
}

if (! function_exists('add_management_page')) {
    function add_management_page(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, callable $callback): string
    {
        return $menuSlug;
    }
}

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): bool
    {
        return true;
    }
}

if (! function_exists('get_posts')) {
    function get_posts(array $args = []): array
    {
        return [];
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(WP_Post $post): string
    {
        return 'http://localhost/post/' . $post->ID;
    }
}

if (! function_exists('get_the_title')) {
    function get_the_title(WP_Post $post): string
    {
        return 'Post ' . $post->ID;
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): string|array|false
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

require_once __DIR__ . '/../plugins/peartree-local-module/includes/class-peartree-local-module-api.php';

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

function resetLeadState(): void
{
    global $mockOptions, $mockTransients, $uuidCounter;
    $mockOptions = [];
    $mockTransients = [];
    $_SERVER = [];
    $uuidCounter = 0;
}

function testCreateLeadSuccessContract(): void
{
    resetLeadState();

    $_SERVER['REMOTE_ADDR'] = '127.0.0.11';

    $request = new WP_REST_Request([
        'name' => 'Jan',
        'email_or_phone' => 'jan@example.com',
        'problem' => 'Cieknie kran',
        'location' => 'Krakow',
        'website' => '',
    ], [], ['user-agent' => 'UnitTest/1.0']);

    $response = PearTreeLocalModuleApi::createLead($request);
    $data = $response->get_data();

    assertSame(201, $response->get_status(), 'createLead should return 201 for valid payload');
    assertSame(true, (bool) ($data['ok'] ?? false), 'createLead should return ok=true for valid payload');
    assertTrue(! empty($data['id']), 'createLead should return generated lead ID');

    echo "✓ PearTreeLocalModuleApi::createLead success contract\n";
}

function testCreateLeadDuplicateContract(): void
{
    resetLeadState();

    $_SERVER['REMOTE_ADDR'] = '127.0.0.12';

    $payload = [
        'name' => 'Anna',
        'email_or_phone' => 'anna@example.com',
        'problem' => 'Auto nie odpala',
        'location' => 'Katowice',
        'website' => '',
    ];

    $first = PearTreeLocalModuleApi::createLead(new WP_REST_Request($payload));
    $second = PearTreeLocalModuleApi::createLead(new WP_REST_Request($payload));

    assertSame(201, $first->get_status(), 'first createLead call should be accepted with 201');
    assertSame(202, $second->get_status(), 'duplicate lead should return 202');
    assertSame(true, (bool) (($second->get_data()['duplicate'] ?? false)), 'duplicate lead response should include duplicate=true');

    echo "✓ PearTreeLocalModuleApi::createLead duplicate contract\n";
}

function testCreateLeadRateLimitContract(): void
{
    resetLeadState();

    $_SERVER['REMOTE_ADDR'] = '127.0.0.13';

    for ($i = 0; $i < 6; $i++) {
        $response = PearTreeLocalModuleApi::createLead(new WP_REST_Request([
            'name' => 'User ' . $i,
            'email_or_phone' => 'u' . $i . '@example.com',
            'problem' => 'Problem ' . $i,
            'location' => 'Warszawa',
            'website' => '',
        ]));
        assertTrue(in_array($response->get_status(), [201, 202], true), 'first 6 attempts should not be rate-limited');
    }

    $limited = PearTreeLocalModuleApi::createLead(new WP_REST_Request([
        'name' => 'User 7',
        'email_or_phone' => 'u7@example.com',
        'problem' => 'Problem 7',
        'location' => 'Warszawa',
        'website' => '',
    ]));

    assertSame(429, $limited->get_status(), '7th attempt in rate window should return 429');
    assertSame('rate_limited', (string) (($limited->get_data()['error'] ?? '')), 'rate-limited response should expose rate_limited error code');

    echo "✓ PearTreeLocalModuleApi::createLead rate-limit contract\n";
}

function testSearchShortQueryReturnsEmptyGroups(): void
{
    resetLeadState();

    $response = PearTreeLocalModuleApi::search(new WP_REST_Request([], ['q' => 'a']));
    $data = $response->get_data();

    assertSame(200, $response->get_status(), 'search should return 200 for short query');
    assertSame([], (array) ($data['guides'] ?? null), 'short search query should return empty guides');
    assertSame([], (array) ($data['specialists'] ?? null), 'short search query should return empty specialists');
    assertSame([], (array) ($data['rankings'] ?? null), 'short search query should return empty rankings');

    echo "✓ PearTreeLocalModuleApi::search short-query contract\n";
}

try {
    echo "Local module API unit tests\n\n";
    testCreateLeadSuccessContract();
    testCreateLeadDuplicateContract();
    testCreateLeadRateLimitContract();
    testSearchShortQueryReturnsEmptyGroups();

    echo "\nOverall: PASS\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAIL: " . $e->getMessage() . "\n");
    exit(1);
}
