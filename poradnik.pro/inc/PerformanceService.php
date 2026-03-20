<?php

declare(strict_types=1);

namespace PoradnikPro;

final class PerformanceService
{
    public static function bootstrap(): void
    {
        add_filter('wp_lazy_loading_enabled', '__return_true');
        add_action('wp_enqueue_scripts', [self::class, 'removeJquery'], 100);
    }

    public static function removeJquery(): void
    {
        if (is_admin()) {
            return;
        }

        wp_deregister_script('jquery');
    }
}
