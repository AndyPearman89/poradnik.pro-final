<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class PearTreeLocalModuleApi
{
    private const LEADS_OPTION = 'peartree_local_module_leads';
    private const LEADS_MAX_ITEMS = 250;
    private const LEADS_RATE_LIMIT_WINDOW = 60;
    private const LEADS_RATE_LIMIT_MAX = 6;
    private const LEADS_DEDUP_WINDOW = 120;

    public static function registerRoutes(): void
    {
        register_rest_route('peartree-local/v1', '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peartree-local/v1', '/echo', [
            'methods' => 'POST',
            'callback' => [self::class, 'echoMessage'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peartree/v1', '/search', [
            'methods' => 'GET',
            'callback' => [self::class, 'search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('peartree/v1', '/guides', [
            'methods' => 'GET',
            'callback' => [self::class, 'guides'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peartree/v1', '/rankings', [
            'methods' => 'GET',
            'callback' => [self::class, 'rankings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peartree/v1', '/listings', [
            'methods' => 'GET',
            'callback' => [self::class, 'listings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('peartree/v1', '/leads', [
            'methods' => 'POST',
            'callback' => [self::class, 'createLead'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function status(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'ok' => true,
            'module' => 'peartree-local-module',
            'time' => gmdate('c'),
        ], 200);
    }

    public static function echoMessage(WP_REST_Request $request): WP_REST_Response
    {
        $params = (array) $request->get_json_params();
        $message = sanitize_text_field((string) ($params['message'] ?? ''));

        return new WP_REST_Response([
            'ok' => true,
            'echo' => $message,
        ], 200);
    }

    public static function search(WP_REST_Request $request): WP_REST_Response
    {
        $query = sanitize_text_field((string) $request->get_param('q'));

        if (strlen(trim($query)) < 2) {
            return new WP_REST_Response([
                'guides' => [],
                'specialists' => [],
                'rankings' => [],
            ], 200);
        }

        $items = self::findContentByQuery($query, 20);

        return new WP_REST_Response([
            'guides' => self::byType($items, 'guide'),
            'specialists' => self::byType($items, 'specialist'),
            'rankings' => self::byType($items, 'ranking'),
        ], 200);
    }

    public static function guides(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'items' => self::latestContent(10, ['post']),
        ], 200);
    }

    public static function rankings(WP_REST_Request $request): WP_REST_Response
    {
        $items = self::latestContent(30, ['post', 'page']);
        $rankings = array_values(array_filter($items, static function (array $item): bool {
            return self::inferType($item['title'] ?? '', $item['excerpt'] ?? '', $item['url'] ?? '') === 'ranking';
        }));

        return new WP_REST_Response([
            'items' => array_slice($rankings, 0, 10),
        ], 200);
    }

    public static function listings(WP_REST_Request $request): WP_REST_Response
    {
        $items = self::latestContent(30, ['post', 'page']);
        $specialists = array_values(array_filter($items, static function (array $item): bool {
            return self::inferType($item['title'] ?? '', $item['excerpt'] ?? '', $item['url'] ?? '') === 'specialist';
        }));

        return new WP_REST_Response([
            'items' => array_slice($specialists, 0, 10),
        ], 200);
    }

    public static function createLead(WP_REST_Request $request): WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        if (! empty($payload['website'])) {
            return new WP_REST_Response([
                'ok' => true,
                'status' => 202,
                'message' => 'Lead accepted.',
            ], 202);
        }

        $ip = self::getRequestIp($request);
        if (self::isRateLimited($ip)) {
            return new WP_REST_Response([
                'ok' => false,
                'status' => 429,
                'message' => 'Too many lead attempts. Please retry shortly.',
                'error' => 'rate_limited',
            ], 429);
        }

        $lead = [
            'name' => sanitize_text_field((string) ($payload['name'] ?? '')),
            'email_or_phone' => sanitize_text_field((string) ($payload['email_or_phone'] ?? '')),
            'problem' => sanitize_textarea_field((string) ($payload['problem'] ?? '')),
            'location' => sanitize_text_field((string) ($payload['location'] ?? '')),
        ];

        if (! self::isLeadValid($lead)) {
            return new WP_REST_Response([
                'ok' => false,
                'status' => 422,
                'message' => 'Missing required lead fields.',
                'error' => 'validation_failed',
            ], 422);
        }

        if (self::isDuplicateLead($lead)) {
            return new WP_REST_Response([
                'ok' => true,
                'status' => 202,
                'message' => 'Lead accepted.',
                'duplicate' => true,
            ], 202);
        }

        $entry = [
            'id' => wp_generate_uuid4(),
            'created_at' => gmdate('c'),
            'name' => $lead['name'],
            'email_or_phone' => $lead['email_or_phone'],
            'problem' => $lead['problem'],
            'location' => $lead['location'],
            'ip' => $ip,
            'user_agent' => self::sanitizeUserAgent((string) $request->get_header('user-agent')),
            'source' => 'peartree-local-module',
        ];

        self::storeLead($entry);
        do_action('peartree_local_module_lead_created', $entry);

        return new WP_REST_Response([
            'ok' => true,
            'status' => 201,
            'message' => 'Lead accepted.',
            'id' => $entry['id'],
        ], 201);
    }

    public static function registerAdminPage(): void
    {
        add_management_page(
            'PearTree Leads Buffer',
            'PearTree Leads',
            'manage_options',
            'peartree-local-module-leads',
            [self::class, 'renderLeadsAdminPage']
        );
    }

    public static function renderLeadsAdminPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'default'));
        }

        $leads = self::getStoredLeads(true);
        $count = count($leads);
        $downloadUrl = wp_nonce_url(
            admin_url('admin-post.php?action=peartree_local_module_export_leads'),
            'peartree_local_module_export_leads'
        );

        echo '<div class="wrap">';
        echo '<h1>PearTree Leads Buffer</h1>';
        echo '<p>Latest buffered leads captured by the /peartree/v1/leads endpoint.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($downloadUrl) . '">Download CSV</a></p>';
        echo '<p><strong>Total buffered:</strong> ' . esc_html((string) $count) . '</p>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>Created At</th>';
        echo '<th>ID</th>';
        echo '<th>Name</th>';
        echo '<th>Contact</th>';
        echo '<th>Location</th>';
        echo '<th>Problem</th>';
        echo '<th>IP</th>';
        echo '<th>Source</th>';
        echo '</tr></thead><tbody>';

        if ($count === 0) {
            echo '<tr><td colspan="8">No leads buffered yet.</td></tr>';
        } else {
            foreach ($leads as $lead) {
                echo '<tr>';
                echo '<td>' . esc_html((string) ($lead['created_at'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['id'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['name'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['email_or_phone'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['location'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['problem'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['ip'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($lead['source'] ?? '')) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public static function exportLeadsCsv(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to export leads.', 'default'));
        }

        check_admin_referer('peartree_local_module_export_leads');

        $leads = self::getStoredLeads(true);
        $filename = 'peartree-leads-' . gmdate('Ymd-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        if ($output === false) {
            wp_die(esc_html__('Failed to open CSV output stream.', 'default'));
        }

        fputcsv($output, ['id', 'created_at', 'name', 'email_or_phone', 'location', 'problem', 'ip', 'user_agent', 'source']);

        foreach ($leads as $lead) {
            fputcsv($output, [
                (string) ($lead['id'] ?? ''),
                (string) ($lead['created_at'] ?? ''),
                (string) ($lead['name'] ?? ''),
                (string) ($lead['email_or_phone'] ?? ''),
                (string) ($lead['location'] ?? ''),
                (string) ($lead['problem'] ?? ''),
                (string) ($lead['ip'] ?? ''),
                (string) ($lead['user_agent'] ?? ''),
                (string) ($lead['source'] ?? ''),
            ]);
        }

        fclose($output);
        exit;
    }

    private static function latestContent(int $limit, array $postTypes): array
    {
        $posts = get_posts([
            'post_type' => $postTypes,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return array_values(array_map(static function (WP_Post $post): array {
            return [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'excerpt' => wp_trim_words(wp_strip_all_tags((string) $post->post_excerpt ?: (string) $post->post_content), 24),
            ];
        }, $posts));
    }

    private static function findContentByQuery(string $query, int $limit): array
    {
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'relevance',
            's' => $query,
        ]);

        return array_values(array_map(static function (WP_Post $post): array {
            $title = (string) get_the_title($post);
            $url = (string) get_permalink($post);
            $excerpt = (string) wp_trim_words(wp_strip_all_tags((string) $post->post_excerpt ?: (string) $post->post_content), 24);

            return [
                'id' => (int) $post->ID,
                'title' => $title,
                'url' => $url,
                'excerpt' => $excerpt,
                'type' => self::inferType($title, $excerpt, $url),
            ];
        }, $posts));
    }

    private static function byType(array $items, string $type): array
    {
        $filtered = array_values(array_filter($items, static function (array $item) use ($type): bool {
            return ($item['type'] ?? '') === $type;
        }));

        return array_slice($filtered, 0, 5);
    }

    private static function inferType(string $title, string $excerpt, string $url): string
    {
        $haystack = strtolower(trim($title . ' ' . $excerpt . ' ' . $url));

        if (preg_match('/ranking|top\s?\d|porownan/i', $haystack) === 1) {
            return 'ranking';
        }

        if (preg_match('/lokal|specjalist|firma|uslug|wykonawc|miast/i', $haystack) === 1) {
            return 'specialist';
        }

        return 'guide';
    }

    private static function isLeadValid(array $lead): bool
    {
        foreach (['name', 'email_or_phone', 'problem', 'location'] as $field) {
            if (trim((string) ($lead[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private static function isRateLimited(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $key = 'peartree_lm_rl_' . md5($ip);
        $attempts = (int) get_transient($key);

        if ($attempts >= self::LEADS_RATE_LIMIT_MAX) {
            return true;
        }

        set_transient($key, $attempts + 1, self::LEADS_RATE_LIMIT_WINDOW);
        return false;
    }

    private static function isDuplicateLead(array $lead): bool
    {
        $normalized = [
            'name' => strtolower(trim((string) ($lead['name'] ?? ''))),
            'email_or_phone' => strtolower(trim((string) ($lead['email_or_phone'] ?? ''))),
            'problem' => strtolower(trim((string) ($lead['problem'] ?? ''))),
            'location' => strtolower(trim((string) ($lead['location'] ?? ''))),
        ];

        $key = 'peartree_lm_dup_' . md5(wp_json_encode($normalized));
        if (get_transient($key) !== false) {
            return true;
        }

        set_transient($key, '1', self::LEADS_DEDUP_WINDOW);
        return false;
    }

    private static function getRequestIp(WP_REST_Request $request): string
    {
        $forwardedFor = (string) $request->get_header('x-forwarded-for');
        if ($forwardedFor !== '') {
            $parts = array_map('trim', explode(',', $forwardedFor));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP) !== false) {
                    return $part;
                }
            }
        }

        $remoteAddr = (string) $request->get_header('x-real-ip');
        if (filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
            return $remoteAddr;
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $candidate = sanitize_text_field((string) $_SERVER['REMOTE_ADDR']);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return '';
    }

    private static function sanitizeUserAgent(string $value): string
    {
        $clean = sanitize_text_field($value);
        if (strlen($clean) <= 190) {
            return $clean;
        }

        return substr($clean, 0, 190);
    }

    private static function getStoredLeads(bool $latestFirst = false): array
    {
        $store = get_option(self::LEADS_OPTION, []);
        if (! is_array($store)) {
            return [];
        }

        if ($latestFirst) {
            $store = array_reverse($store);
        }

        return $store;
    }

    private static function storeLead(array $entry): void
    {
        $store = self::getStoredLeads(false);

        $store[] = $entry;
        if (count($store) > self::LEADS_MAX_ITEMS) {
            $store = array_slice($store, -1 * self::LEADS_MAX_ITEMS);
        }

        update_option(self::LEADS_OPTION, $store, false);
    }
}
