<?php

declare(strict_types=1);

namespace PoradnikPro;

/**
 * Caching Service
 *
 * Provides intelligent caching for expensive operations to improve performance.
 *
 * **Features:**
 * - WordPress transient-based caching
 * - Cache key generation with context awareness
 * - Automatic cache invalidation
 * - Performance monitoring
 * - Cache hit/miss statistics
 *
 * **Use Cases:**
 * - Schema.org JSON-LD generation
 * - Internal linking queries
 * - Related content recommendations
 * - Expensive database queries
 */
final class CacheService
{
    private const STATS_OPTION_KEY = 'poradnik_pro_cache_stats';
    private const DEFAULT_TTL = 3600; // 1 hour

    /**
     * Cache namespaces for different data types
     */
    public const NS_SCHEMA = 'schema';
    public const NS_LINKS = 'links';
    public const NS_CONTENT = 'content';
    public const NS_QUERY = 'query';

    /**
     * Get cached value or compute and cache
     *
     * @param string $namespace Cache namespace
     * @param string $key Cache key
     * @param callable $callback Callback to compute value if not cached
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public static function remember(string $namespace, string $key, callable $callback, int $ttl = self::DEFAULT_TTL)
    {
        $cacheKey = self::buildKey($namespace, $key);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            self::recordHit($namespace);
            return $cached;
        }

        $startTime = microtime(true);
        $value = $callback();
        $duration = (microtime(true) - $startTime) * 1000;

        set_transient($cacheKey, $value, $ttl);

        self::recordMiss($namespace);
        LoggerService::performance("Cache miss: {$namespace}:{$key}", $duration);

        return $value;
    }

    /**
     * Get cached value
     *
     * @param string $namespace Cache namespace
     * @param string $key Cache key
     * @param mixed $default Default value if not cached
     * @return mixed Cached value or default
     */
    public static function get(string $namespace, string $key, $default = null)
    {
        $cacheKey = self::buildKey($namespace, $key);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            self::recordHit($namespace);
            return $cached;
        }

        self::recordMiss($namespace);
        return $default;
    }

    /**
     * Set cached value
     *
     * @param string $namespace Cache namespace
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success
     */
    public static function set(string $namespace, string $key, $value, int $ttl = self::DEFAULT_TTL): bool
    {
        $cacheKey = self::buildKey($namespace, $key);
        return set_transient($cacheKey, $value, $ttl);
    }

    /**
     * Delete cached value
     *
     * @param string $namespace Cache namespace
     * @param string $key Cache key
     * @return bool Success
     */
    public static function forget(string $namespace, string $key): bool
    {
        $cacheKey = self::buildKey($namespace, $key);
        return delete_transient($cacheKey);
    }

    /**
     * Clear all cached values in namespace
     *
     * @param string $namespace Cache namespace
     * @return int Number of entries cleared
     */
    public static function flush(string $namespace): int
    {
        global $wpdb;

        $prefix = self::buildKey($namespace, '');

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $prefix) . '%',
                $wpdb->esc_like('_transient_timeout_' . $prefix) . '%'
            )
        );

        LoggerService::info("Cache flush: {$namespace}", ['deleted' => $deleted]);

        return (int) $deleted;
    }

    /**
     * Clear all cached values in all namespaces
     *
     * @return int Number of entries cleared
     */
    public static function flushAll(): int
    {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_poradnik_pro_cache_') . '%',
                $wpdb->esc_like('_transient_timeout_poradnik_pro_cache_') . '%'
            )
        );

        LoggerService::info('Cache flush all', ['deleted' => $deleted]);

        return (int) $deleted;
    }

    /**
     * Build cache key with namespace
     *
     * @param string $namespace Cache namespace
     * @param string $key Cache key
     * @return string Full cache key
     */
    private static function buildKey(string $namespace, string $key): string
    {
        $safeNamespace = sanitize_key($namespace);
        $safeKey = sanitize_key($key);

        return 'poradnik_pro_cache_' . $safeNamespace . '_' . $safeKey;
    }

    /**
     * Record cache hit for statistics
     *
     * @param string $namespace Cache namespace
     * @return void
     */
    private static function recordHit(string $namespace): void
    {
        if (! self::shouldTrackStats()) {
            return;
        }

        $stats = self::getStats();
        $stats['hits']++;
        $stats['by_namespace'][$namespace]['hits'] = ($stats['by_namespace'][$namespace]['hits'] ?? 0) + 1;

        update_option(self::STATS_OPTION_KEY, $stats, false);
    }

    /**
     * Record cache miss for statistics
     *
     * @param string $namespace Cache namespace
     * @return void
     */
    private static function recordMiss(string $namespace): void
    {
        if (! self::shouldTrackStats()) {
            return;
        }

        $stats = self::getStats();
        $stats['misses']++;
        $stats['by_namespace'][$namespace]['misses'] = ($stats['by_namespace'][$namespace]['misses'] ?? 0) + 1;

        update_option(self::STATS_OPTION_KEY, $stats, false);
    }

    /**
     * Check if stats tracking is enabled
     *
     * @return bool Should track
     */
    private static function shouldTrackStats(): bool
    {
        // Only track in debug mode to avoid overhead
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Get cache statistics
     *
     * @return array{hits: int, misses: int, hit_rate: float, by_namespace: array<string, array{hits: int, misses: int}>}
     */
    public static function getStats(): array
    {
        $stats = get_option(self::STATS_OPTION_KEY, [
            'hits' => 0,
            'misses' => 0,
            'by_namespace' => [],
        ]);

        if (! is_array($stats)) {
            $stats = ['hits' => 0, 'misses' => 0, 'by_namespace' => []];
        }

        $hits = (int) ($stats['hits'] ?? 0);
        $misses = (int) ($stats['misses'] ?? 0);
        $total = $hits + $misses;

        $stats['hit_rate'] = $total > 0 ? ($hits / $total) * 100 : 0.0;

        return $stats;
    }

    /**
     * Reset cache statistics
     *
     * @return bool Success
     */
    public static function resetStats(): bool
    {
        return update_option(self::STATS_OPTION_KEY, [
            'hits' => 0,
            'misses' => 0,
            'by_namespace' => [],
        ], false);
    }

    /**
     * Register admin page for cache management
     *
     * @return void
     */
    public static function registerAdminPage(): void
    {
        add_submenu_page(
            'poradnik-pro-kpi',
            __('Cache Management', 'poradnik-pro'),
            __('Cache', 'poradnik-pro'),
            'manage_options',
            'poradnik-pro-cache',
            [self::class, 'renderAdminPage']
        );
    }

    /**
     * Render admin page for cache management
     *
     * @return void
     */
    public static function renderAdminPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Handle flush actions
        if (isset($_POST['flush_cache']) && check_admin_referer('poradnik_pro_flush_cache')) {
            $namespace = sanitize_key((string) ($_POST['namespace'] ?? 'all'));

            if ($namespace === 'all') {
                $deleted = self::flushAll();
                $message = sprintf(__('%d cache entries cleared (all namespaces).', 'poradnik-pro'), $deleted);
            } else {
                $deleted = self::flush($namespace);
                $message = sprintf(__('%d cache entries cleared in namespace: %s.', 'poradnik-pro'), $deleted, $namespace);
            }

            echo '<div class="notice notice-success"><p>' . esc_html($message) . '</p></div>';
        }

        if (isset($_POST['reset_stats']) && check_admin_referer('poradnik_pro_reset_cache_stats')) {
            self::resetStats();
            echo '<div class="notice notice-success"><p>' . esc_html__('Cache statistics reset.', 'poradnik-pro') . '</p></div>';
        }

        $stats = self::getStats();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Cache Management', 'poradnik-pro') . '</h1>';

        echo '<div class="card">';
        echo '<h2>' . esc_html__('Cache Statistics', 'poradnik-pro') . '</h2>';
        echo '<p><strong>' . esc_html__('Total hits:', 'poradnik-pro') . '</strong> ' . esc_html((string) $stats['hits']) . '</p>';
        echo '<p><strong>' . esc_html__('Total misses:', 'poradnik-pro') . '</strong> ' . esc_html((string) $stats['misses']) . '</p>';
        echo '<p><strong>' . esc_html__('Hit rate:', 'poradnik-pro') . '</strong> ' . esc_html(number_format((float) $stats['hit_rate'], 2)) . '%</p>';
        echo '</div>';

        $byNamespace = (array) ($stats['by_namespace'] ?? []);
        if ($byNamespace !== []) {
            echo '<h2>' . esc_html__('Statistics by Namespace', 'poradnik-pro') . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Namespace', 'poradnik-pro') . '</th>';
            echo '<th>' . esc_html__('Hits', 'poradnik-pro') . '</th>';
            echo '<th>' . esc_html__('Misses', 'poradnik-pro') . '</th>';
            echo '<th>' . esc_html__('Hit Rate', 'poradnik-pro') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($byNamespace as $namespace => $nsStat) {
                $hits = (int) ($nsStat['hits'] ?? 0);
                $misses = (int) ($nsStat['misses'] ?? 0);
                $total = $hits + $misses;
                $hitRate = $total > 0 ? ($hits / $total) * 100 : 0.0;

                echo '<tr>';
                echo '<td>' . esc_html((string) $namespace) . '</td>';
                echo '<td>' . esc_html((string) $hits) . '</td>';
                echo '<td>' . esc_html((string) $misses) . '</td>';
                echo '<td>' . esc_html(number_format($hitRate, 2)) . '%</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '<h2 style="margin-top: 24px;">' . esc_html__('Cache Actions', 'poradnik-pro') . '</h2>';

        echo '<form method="post" style="margin-bottom: 10px;">';
        wp_nonce_field('poradnik_pro_flush_cache');
        echo '<input type="hidden" name="namespace" value="all">';
        submit_button(__('Clear All Caches', 'poradnik-pro'), 'delete', 'flush_cache', false);
        echo '</form>';

        echo '<form method="post" style="margin-bottom: 10px;">';
        wp_nonce_field('poradnik_pro_flush_cache');
        echo '<input type="hidden" name="namespace" value="' . esc_attr(self::NS_SCHEMA) . '">';
        submit_button(__('Clear Schema Cache', 'poradnik-pro'), 'secondary', 'flush_cache', false);
        echo '</form>';

        echo '<form method="post" style="margin-bottom: 10px;">';
        wp_nonce_field('poradnik_pro_flush_cache');
        echo '<input type="hidden" name="namespace" value="' . esc_attr(self::NS_LINKS) . '">';
        submit_button(__('Clear Links Cache', 'poradnik-pro'), 'secondary', 'flush_cache', false);
        echo '</form>';

        echo '<form method="post" style="margin-bottom: 10px;">';
        wp_nonce_field('poradnik_pro_reset_cache_stats');
        submit_button(__('Reset Statistics', 'poradnik-pro'), 'secondary', 'reset_stats', false);
        echo '</form>';

        echo '</div>';
    }
}
