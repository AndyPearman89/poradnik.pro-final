<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('PORADNIK_PRO_THEME_PATH', get_template_directory());
define('PORADNIK_PRO_THEME_URL', get_template_directory_uri());
define('PORADNIK_PRO_THEME_VERSION', wp_get_theme()->get('Version') ?: '0.1.0');

require_once PORADNIK_PRO_THEME_PATH . '/inc/App.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/Enqueue.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/ApiClient.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/SearchService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/LeadService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/SeoService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/SchemaService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/InternalLinkingService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/MonetizationService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/ExperimentService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/AnalyticsService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/UiService.php';
require_once PORADNIK_PRO_THEME_PATH . '/inc/PerformanceService.php';

\PoradnikPro\App::boot();