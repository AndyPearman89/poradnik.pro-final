<?php
/**
 * Plugin Name: PearTree Local Module
 * Description: Minimalny modul E2E: endpoint REST + shortcode UI + seed danych demo.
 * Version: 1.0.0
 * Author: PearTree
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-peartree-local-module-api.php';
require_once __DIR__ . '/includes/class-peartree-local-module-ui.php';

final class PearTreeLocalModule
{
    public static function bootstrap(): void
    {
        add_action('rest_api_init', [PearTreeLocalModuleApi::class, 'registerRoutes']);
        add_shortcode('peartree_local_module', [PearTreeLocalModuleUi::class, 'renderShortcode']);
    }

    public static function activate(): void
    {
        if (get_option('peartree_local_module_seeded') === '1') {
            return;
        }

        if (get_page_by_path('modul-e2e') === null) {
            wp_insert_post([
                'post_title' => 'Modul E2E',
                'post_name' => 'modul-e2e',
                'post_content' => '[peartree_local_module]',
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
        }

        update_option('peartree_local_module_seeded', '1', false);
    }
}

register_activation_hook(__FILE__, [PearTreeLocalModule::class, 'activate']);
PearTreeLocalModule::bootstrap();
