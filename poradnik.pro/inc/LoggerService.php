<?php

declare(strict_types=1);

namespace PoradnikPro;

/**
 * Centralized Error Handling and Logging Service
 *
 * Provides structured logging, error tracking, and exception handling for the theme.
 *
 * **Features:**
 * - Structured logging with severity levels
 * - Context-aware error messages
 * - Integration with WordPress debug log
 * - Performance metrics tracking
 * - Error aggregation and reporting
 *
 * **Log Levels:**
 * - DEBUG: Detailed diagnostic information
 * - INFO: Informational messages
 * - NOTICE: Normal but significant events
 * - WARNING: Warning messages
 * - ERROR: Error conditions
 * - CRITICAL: Critical conditions requiring immediate attention
 */
final class LoggerService
{
    private const OPTION_KEY = 'poradnik_pro_error_log';
    private const MAX_LOG_ENTRIES = 1000;
    private const LOG_RETENTION_DAYS = 7;

    /**
     * Log levels (PSR-3 compatible)
     */
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const NOTICE = 'NOTICE';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Log a notice
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log(self::NOTICE, $message, $context);
    }

    /**
     * Log a warning
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Log an error
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Log a critical error
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Log a performance metric
     *
     * @param string $operation Operation name
     * @param float $durationMs Duration in milliseconds
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function performance(string $operation, float $durationMs, array $context = []): void
    {
        $context['duration_ms'] = round($durationMs, 2);
        $context['operation'] = $operation;

        if ($durationMs > 1000) {
            self::warning("Slow operation: {$operation}", $context);
        } else {
            self::debug("Performance: {$operation}", $context);
        }
    }

    /**
     * Log an exception
     *
     * @param \Throwable $exception Exception to log
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public static function exception(\Throwable $exception, array $context = []): void
    {
        $context['exception_class'] = get_class($exception);
        $context['exception_code'] = $exception->getCode();
        $context['exception_file'] = $exception->getFile();
        $context['exception_line'] = $exception->getLine();
        $context['exception_trace'] = $exception->getTraceAsString();

        self::error($exception->getMessage(), $context);
    }

    /**
     * Core logging function
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    private static function log(string $level, string $message, array $context): void
    {
        $config = self::config();

        // Check if logging is enabled for this level
        if (! self::shouldLog($level, $config['min_level'])) {
            return;
        }

        $entry = self::buildLogEntry($level, $message, $context);

        // Write to WordPress debug log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            self::writeToDebugLog($entry);
        }

        // Store in options table for admin viewing
        if ($config['store_in_db']) {
            self::storeLogEntry($entry);
        }

        // Trigger action hook for external logging integrations
        do_action('poradnik_pro_log', $entry);
    }

    /**
     * Build structured log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return array<string, mixed> Log entry
     */
    private static function buildLogEntry(string $level, string $message, array $context): array
    {
        return [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request_uri' => sanitize_text_field((string) ($_SERVER['REQUEST_URI'] ?? '')),
            'user_agent' => sanitize_text_field((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'ip' => self::getClientIp(),
        ];
    }

    /**
     * Write log entry to WordPress debug log
     *
     * @param array<string, mixed> $entry Log entry
     * @return void
     */
    private static function writeToDebugLog(array $entry): void
    {
        $level = (string) ($entry['level'] ?? 'INFO');
        $message = (string) ($entry['message'] ?? '');
        $context = (array) ($entry['context'] ?? []);

        $logLine = sprintf(
            '[Poradnik.pro][%s] %s',
            $level,
            $message
        );

        if ($context !== []) {
            $logLine .= ' | Context: ' . wp_json_encode($context);
        }

        error_log($logLine);
    }

    /**
     * Store log entry in database
     *
     * @param array<string, mixed> $entry Log entry
     * @return void
     */
    private static function storeLogEntry(array $entry): void
    {
        $logs = get_option(self::OPTION_KEY, []);

        if (! is_array($logs)) {
            $logs = [];
        }

        // Add new entry
        array_unshift($logs, $entry);

        // Limit to MAX_LOG_ENTRIES
        $logs = array_slice($logs, 0, self::MAX_LOG_ENTRIES);

        // Prune old entries
        $logs = self::pruneLogs($logs);

        update_option(self::OPTION_KEY, $logs, false);
    }

    /**
     * Prune logs older than retention period
     *
     * @param array<array<string, mixed>> $logs Log entries
     * @return array<array<string, mixed>> Pruned logs
     */
    private static function pruneLogs(array $logs): array
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . self::LOG_RETENTION_DAYS . ' days'));

        return array_filter($logs, static function (array $entry) use ($cutoff): bool {
            $timestamp = (string) ($entry['timestamp'] ?? '');
            return $timestamp >= $cutoff;
        });
    }

    /**
     * Check if log level should be logged
     *
     * @param string $level Log level
     * @param string $minLevel Minimum level to log
     * @return bool Should log
     */
    private static function shouldLog(string $level, string $minLevel): bool
    {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
        ];

        $currentPriority = $levels[$level] ?? 0;
        $minPriority = $levels[$minLevel] ?? 0;

        return $currentPriority >= $minPriority;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (! empty($_SERVER[$header])) {
                $ip = sanitize_text_field((string) $_SERVER[$header]);
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
     * Get logger configuration
     *
     * @return array{min_level: string, store_in_db: bool}
     */
    private static function config(): array
    {
        $config = get_option('poradnik_pro_logger_config', []);

        if (! is_array($config)) {
            $config = [];
        }

        // In production, default to WARNING; in debug mode, log everything
        $defaultMinLevel = (defined('WP_DEBUG') && WP_DEBUG) ? self::DEBUG : self::WARNING;

        return [
            'min_level' => (string) ($config['min_level'] ?? $defaultMinLevel),
            'store_in_db' => (bool) ($config['store_in_db'] ?? true),
        ];
    }

    /**
     * Get recent log entries (for admin viewing)
     *
     * @param int $limit Maximum number of entries
     * @param string|null $level Filter by level
     * @return array<array<string, mixed>> Log entries
     */
    public static function getRecentLogs(int $limit = 100, ?string $level = null): array
    {
        $logs = get_option(self::OPTION_KEY, []);

        if (! is_array($logs)) {
            return [];
        }

        if ($level !== null) {
            $logs = array_filter($logs, static function (array $entry) use ($level): bool {
                return ($entry['level'] ?? '') === $level;
            });
        }

        return array_slice($logs, 0, $limit);
    }

    /**
     * Clear all logs (admin action)
     *
     * @return bool Success
     */
    public static function clearLogs(): bool
    {
        return delete_option(self::OPTION_KEY);
    }

    /**
     * Get error statistics
     *
     * @return array{total: int, by_level: array<string, int>, last_error: string|null}
     */
    public static function getStats(): array
    {
        $logs = get_option(self::OPTION_KEY, []);

        if (! is_array($logs)) {
            return [
                'total' => 0,
                'by_level' => [],
                'last_error' => null,
            ];
        }

        $byLevel = [];
        $lastError = null;

        foreach ($logs as $entry) {
            $level = (string) ($entry['level'] ?? 'UNKNOWN');
            $byLevel[$level] = ($byLevel[$level] ?? 0) + 1;

            if (in_array($level, [self::ERROR, self::CRITICAL], true) && $lastError === null) {
                $lastError = (string) ($entry['timestamp'] ?? '') . ': ' . (string) ($entry['message'] ?? '');
            }
        }

        return [
            'total' => count($logs),
            'by_level' => $byLevel,
            'last_error' => $lastError,
        ];
    }

    /**
     * Register admin page for viewing logs
     *
     * @return void
     */
    public static function registerAdminPage(): void
    {
        add_submenu_page(
            'poradnik-pro-kpi',
            __('Error Logs', 'poradnik-pro'),
            __('Logs', 'poradnik-pro'),
            'manage_options',
            'poradnik-pro-logs',
            [self::class, 'renderAdminPage']
        );
    }

    /**
     * Render admin page for viewing logs
     *
     * @return void
     */
    public static function renderAdminPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Handle clear logs action
        if (isset($_POST['clear_logs']) && check_admin_referer('poradnik_pro_clear_logs')) {
            self::clearLogs();
            echo '<div class="notice notice-success"><p>' . esc_html__('Logs cleared successfully.', 'poradnik-pro') . '</p></div>';
        }

        $stats = self::getStats();
        $logs = self::getRecentLogs(100);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Error Logs', 'poradnik-pro') . '</h1>';

        echo '<div class="card">';
        echo '<h2>' . esc_html__('Statistics', 'poradnik-pro') . '</h2>';
        echo '<p><strong>' . esc_html__('Total entries:', 'poradnik-pro') . '</strong> ' . esc_html((string) $stats['total']) . '</p>';

        foreach ($stats['by_level'] as $level => $count) {
            echo '<p><strong>' . esc_html($level) . ':</strong> ' . esc_html((string) $count) . '</p>';
        }

        if ($stats['last_error'] !== null) {
            echo '<p><strong>' . esc_html__('Last error:', 'poradnik-pro') . '</strong> ' . esc_html($stats['last_error']) . '</p>';
        }
        echo '</div>';

        echo '<form method="post" style="margin: 20px 0;">';
        wp_nonce_field('poradnik_pro_clear_logs');
        submit_button(__('Clear All Logs', 'poradnik-pro'), 'delete', 'clear_logs', false);
        echo '</form>';

        if ($logs === []) {
            echo '<p>' . esc_html__('No log entries.', 'poradnik-pro') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Timestamp', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Level', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Message', 'poradnik-pro') . '</th>';
        echo '<th>' . esc_html__('Context', 'poradnik-pro') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $entry) {
            $level = (string) ($entry['level'] ?? 'UNKNOWN');
            $levelClass = strtolower($level);

            echo '<tr>';
            echo '<td>' . esc_html((string) ($entry['timestamp'] ?? '')) . '</td>';
            echo '<td><span class="log-level-' . esc_attr($levelClass) . '">' . esc_html($level) . '</span></td>';
            echo '<td>' . esc_html((string) ($entry['message'] ?? '')) . '</td>';
            echo '<td><pre style="font-size:11px;max-width:400px;overflow:auto;">' . esc_html(wp_json_encode($entry['context'] ?? [], JSON_PRETTY_PRINT)) . '</pre></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        // Add inline CSS for log levels
        echo '<style>
            .log-level-debug { color: #888; }
            .log-level-info { color: #2271b1; }
            .log-level-notice { color: #2271b1; font-weight: bold; }
            .log-level-warning { color: #dba617; font-weight: bold; }
            .log-level-error { color: #d63638; font-weight: bold; }
            .log-level-critical { color: #fff; background: #d63638; padding: 2px 6px; border-radius: 3px; }
        </style>';
    }
}
