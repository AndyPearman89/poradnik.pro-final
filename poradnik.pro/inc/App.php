<?php

declare(strict_types=1);

namespace PoradnikPro;

final class App
{
    public static function boot(): void
    {
        add_action('after_setup_theme', [self::class, 'setupTheme']);
        add_action('wp_enqueue_scripts', [Enqueue::class, 'register']);
        add_action('init', [PerformanceService::class, 'bootstrap']);
        add_action('admin_menu', [AnalyticsService::class, 'registerAdminPage']);
        add_action('rest_api_init', [AnalyticsService::class, 'registerRestRoutes']);
        add_action('wp_head', [SeoService::class, 'renderMeta'], 1);
        add_action('wp_head', [SchemaService::class, 'renderSchema'], 20);
        add_filter('the_content', [InternalLinkingService::class, 'appendRelatedLinks']);
    }

    public static function setupTheme(): void
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]);

        register_nav_menus([
            'primary' => __('Primary Menu', 'poradnik-pro'),
            'footer' => __('Footer Menu', 'poradnik-pro'),
        ]);
    }
}