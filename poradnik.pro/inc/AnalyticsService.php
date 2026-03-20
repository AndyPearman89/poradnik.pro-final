<?php

declare(strict_types=1);

namespace PoradnikPro;

use WP_REST_Request;
use WP_REST_Response;

final class AnalyticsService
{
    private const OPTION_KEY = 'poradnik_pro_kpi_store';
    private const OPTION_CONFIG_KEY = 'poradnik_pro_kpi_config';

    public static function registerRestRoutes(): void
    {
        register_rest_route('peartree/v1', '/track', [
            'methods' => 'POST',
            'callback' => [self::class, 'ingestEvent'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function registerAdminPage(): void
    {
        add_menu_page(
            __('Poradnik KPI', 'poradnik-pro'),
            __('Poradnik KPI', 'poradnik-pro'),
            'manage_options',
            'poradnik-pro-kpi',
            [self::class, 'renderAdminPage'],
            'dashicons-chart-line',
            58
        );
    }

    public static function ingestEvent(WP_REST_Request $request): WP_REST_Response
    {
        $payload = (array) $request->get_json_params();
        $eventName = sanitize_key((string) ($payload['eventName'] ?? 'unknown'));
        $eventData = (array) ($payload['payload'] ?? []);
        $source = sanitize_key((string) ($eventData['source'] ?? $eventData['channel'] ?? 'unknown'));
        $config = self::config();

        $store = get_option(self::OPTION_KEY, []);
        $day = gmdate('Y-m-d');

        if (! isset($store[$day])) {
            $store[$day] = [
                'events' => [],
                'sources' => [],
                'revenue' => [
                    'affiliate_clicks' => 0,
                    'lead_success' => 0,
                    'estimated_affiliate_revenue' => 0.0,
                    'estimated_lead_revenue' => 0.0,
                ],
            ];
        }

        $store[$day]['events'][$eventName] = (int) ($store[$day]['events'][$eventName] ?? 0) + 1;
        $store[$day]['sources'][$source] = (int) ($store[$day]['sources'][$source] ?? 0) + 1;

        if ($eventName === 'cta_click' && $source === 'affiliate') {
            $store[$day]['revenue']['affiliate_clicks']++;
            $store[$day]['revenue']['estimated_affiliate_revenue'] += (float) $config['affiliate_value_per_click'];
        }

        if ($eventName === 'lead_submit_success') {
            $store[$day]['revenue']['lead_success']++;
            $store[$day]['revenue']['estimated_lead_revenue'] += (float) $config['lead_value_per_success'];
        }

        update_option(self::OPTION_KEY, $store, false);

        return new WP_REST_Response([
            'ok' => true,
            'event' => $eventName,
        ], 202);
    }

    public static function renderAdminPage(): void
    {
        self::handleConfigPost();

        $config = self::config();
        $store = (array) get_option(self::OPTION_KEY, []);
        krsort($store);

        $rows = array_slice($store, 0, 14, true);
        $summary = self::buildSummary($rows);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Poradnik KPI Dashboard', 'poradnik-pro') . '</h1>';
        echo '<p>' . esc_html__('Attribution -> Monetization, ostatnie 14 dni.', 'poradnik-pro') . '</p>';

        echo '<h2>' . esc_html__('Kalibracja estymacji revenue', 'poradnik-pro') . '</h2>';
        echo '<form method="post" style="margin-bottom:20px;">';
        wp_nonce_field('poradnik_pro_kpi_config', 'poradnik_pro_kpi_nonce');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="affiliate_value_per_click">' . esc_html__('Affiliate PLN / click', 'poradnik-pro') . '</label></th>';
        echo '<td><input name="affiliate_value_per_click" id="affiliate_value_per_click" type="number" min="0" step="0.01" value="' . esc_attr((string) $config['affiliate_value_per_click']) . '"></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="lead_value_per_success">' . esc_html__('Lead PLN / success', 'poradnik-pro') . '</label></th>';
        echo '<td><input name="lead_value_per_success" id="lead_value_per_success" type="number" min="0" step="0.01" value="' . esc_attr((string) $config['lead_value_per_success']) . '"></td>';
        echo '</tr>';
        echo '</tbody></table>';
        submit_button(__('Zapisz kalibracje', 'poradnik-pro'), 'primary', 'poradnik_pro_kpi_save');
        echo '</form>';

        echo '<h2>' . esc_html__('Podsumowanie 14 dni', 'poradnik-pro') . '</h2>';
        echo '<p>' . esc_html__('Lead success: ', 'poradnik-pro') . esc_html((string) $summary['lead_success']) . ' | ';
        echo esc_html__('Affiliate clicks: ', 'poradnik-pro') . esc_html((string) $summary['affiliate_clicks']) . ' | ';
        echo esc_html__('Est. total revenue: ', 'poradnik-pro') . esc_html(number_format((float) $summary['estimated_total_revenue'], 2, '.', ' ')) . ' PLN</p>';

        if ($rows === []) {
            echo '<p>' . esc_html__('Brak danych eventowych.', 'poradnik-pro') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Dzien', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Lead success', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Affiliate clicks', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Est. lead revenue', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Est. affiliate revenue', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Top source', 'poradnik-pro') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $day => $data) {
            $revenue = (array) ($data['revenue'] ?? []);
            $sources = (array) ($data['sources'] ?? []);
            arsort($sources);
            $topSource = (string) (array_key_first($sources) ?? 'unknown');

            echo '<tr>';
            echo '<td>' . esc_html((string) $day) . '</td>';
            echo '<td>' . esc_html((string) ($revenue['lead_success'] ?? 0)) . '</td>';
            echo '<td>' . esc_html((string) ($revenue['affiliate_clicks'] ?? 0)) . '</td>';
            echo '<td>' . esc_html(number_format((float) ($revenue['estimated_lead_revenue'] ?? 0), 2, '.', ' ')) . ' PLN</td>';
            echo '<td>' . esc_html(number_format((float) ($revenue['estimated_affiliate_revenue'] ?? 0), 2, '.', ' ')) . ' PLN</td>';
            echo '<td>' . esc_html($topSource) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<h2 style="margin-top:24px;">' . esc_html__('Top sources (14 dni)', 'poradnik-pro') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Source', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Visits/events', 'poradnik-pro') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($summary['top_sources'] as $source => $count) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $source) . '</td>';
            echo '<td>' . esc_html((string) $count) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private static function config(): array
    {
        $config = (array) get_option(self::OPTION_CONFIG_KEY, []);

        return [
            'affiliate_value_per_click' => (float) ($config['affiliate_value_per_click'] ?? 1.5),
            'lead_value_per_success' => (float) ($config['lead_value_per_success'] ?? 25.0),
        ];
    }

    private static function handleConfigPost(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (! isset($_POST['poradnik_pro_kpi_save'])) {
            return;
        }

        $nonce = sanitize_text_field((string) ($_POST['poradnik_pro_kpi_nonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'poradnik_pro_kpi_config')) {
            return;
        }

        $affiliate = isset($_POST['affiliate_value_per_click']) ? (float) $_POST['affiliate_value_per_click'] : 1.5;
        $lead = isset($_POST['lead_value_per_success']) ? (float) $_POST['lead_value_per_success'] : 25.0;

        update_option(self::OPTION_CONFIG_KEY, [
            'affiliate_value_per_click' => max(0, $affiliate),
            'lead_value_per_success' => max(0, $lead),
        ], false);
    }

    private static function buildSummary(array $rows): array
    {
        $leadSuccess = 0;
        $affiliateClicks = 0;
        $leadRevenue = 0.0;
        $affiliateRevenue = 0.0;
        $sourceTotals = [];

        foreach ($rows as $data) {
            $revenue = (array) ($data['revenue'] ?? []);
            $sources = (array) ($data['sources'] ?? []);

            $leadSuccess += (int) ($revenue['lead_success'] ?? 0);
            $affiliateClicks += (int) ($revenue['affiliate_clicks'] ?? 0);
            $leadRevenue += (float) ($revenue['estimated_lead_revenue'] ?? 0);
            $affiliateRevenue += (float) ($revenue['estimated_affiliate_revenue'] ?? 0);

            foreach ($sources as $source => $count) {
                $sourceTotals[(string) $source] = (int) ($sourceTotals[(string) $source] ?? 0) + (int) $count;
            }
        }

        arsort($sourceTotals);

        return [
            'lead_success' => $leadSuccess,
            'affiliate_clicks' => $affiliateClicks,
            'estimated_total_revenue' => $leadRevenue + $affiliateRevenue,
            'top_sources' => array_slice($sourceTotals, 0, 10, true),
        ];
    }
}
