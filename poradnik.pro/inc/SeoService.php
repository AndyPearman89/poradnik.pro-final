<?php

declare(strict_types=1);

namespace PoradnikPro;

final class SeoService
{
    public static function renderMeta(): void
    {
        $title = wp_get_document_title();
        $canonical = self::canonicalUrl();
        $description = self::description();

        if ($description === '') {
            return;
        }

        echo '<meta name="description" content="' . esc_attr($description) . '">' . PHP_EOL;
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . PHP_EOL;
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . PHP_EOL;
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . PHP_EOL;
    }

    private static function canonicalUrl(): string
    {
        if (is_singular()) {
            return (string) get_permalink();
        }

        return (string) home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
    }

    private static function description(): string
    {
        if (is_singular()) {
            return wp_strip_all_tags((string) get_the_excerpt());
        }

        if (is_archive()) {
            return __('Porownuj poradniki, rankingi i lokalnych specjalistow dopasowanych do Twojego problemu.', 'poradnik-pro');
        }

        if (is_front_page()) {
            return __('Poradnik.pro: problem -> poradnik -> specjalista -> lead. Znajdz odpowiedzi i szybki kontakt.', 'poradnik-pro');
        }

        return '';
    }
}
