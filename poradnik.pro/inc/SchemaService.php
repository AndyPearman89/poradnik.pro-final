<?php

declare(strict_types=1);

namespace PoradnikPro;

final class SchemaService
{
    public static function renderSchema(): void
    {
        $path = trim((string) wp_parse_url((string) home_url(add_query_arg([], $GLOBALS['wp']->request ?? '')), PHP_URL_PATH), '/');

        if (is_front_page()) {
            self::printJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => get_bloginfo('name') . ' Home',
                'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
                'numberOfItems' => 6,
            ]);

            return;
        }

        if (str_contains($path, 'uslugi')) {
            self::printJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                'name' => get_bloginfo('name') . ' Local',
                'url' => home_url('/uslugi/'),
                'areaServed' => 'PL',
            ]);

            return;
        }

        if (str_contains($path, 'ranking')) {
            self::printJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => get_bloginfo('name') . ' Ranking',
                'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            ]);

            return;
        }

        if (str_contains($path, 'pytan') || str_contains($path, 'question')) {
            self::printJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => [[
                    '@type' => 'Question',
                    'name' => get_the_title() ?: __('Najczestsze pytania', 'poradnik-pro'),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags((string) get_the_excerpt()),
                    ],
                ]],
            ]);

            return;
        }

        if (is_singular()) {
            self::printJsonLd([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => get_the_title(),
                'url' => get_permalink(),
                'datePublished' => get_post_time(DATE_W3C),
                'dateModified' => get_post_modified_time(DATE_W3C),
            ]);
        }
    }

    private static function printJsonLd(array $schema): void
    {
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . PHP_EOL;
    }
}
