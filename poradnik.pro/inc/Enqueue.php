<?php

declare(strict_types=1);

namespace PoradnikPro;

final class Enqueue
{
    public static function register(): void
    {
        wp_enqueue_style(
            'poradnik-pro-style',
            PORADNIK_PRO_THEME_URL . '/style.css',
            [],
            PORADNIK_PRO_THEME_VERSION
        );

        $scripts = [
            'core' => [],
            'tracking' => ['core'],
            'attribution' => ['core', 'tracking'],
            'search' => ['core', 'tracking'],
            'leads' => ['core', 'tracking'],
            'ui' => ['core', 'tracking'],
        ];

        foreach ($scripts as $handle => $deps) {
            $fullHandle = 'poradnik-pro-' . $handle;

            wp_enqueue_script(
                $fullHandle,
                PORADNIK_PRO_THEME_URL . '/assets/js/' . $handle . '.js',
                array_map(static fn (string $dep): string => 'poradnik-pro-' . $dep, $deps),
                PORADNIK_PRO_THEME_VERSION,
                true
            );

            wp_script_add_data($fullHandle, 'defer', true);
        }

        wp_localize_script('poradnik-pro-core', 'poradnikProConfig', [
            'apiBase' => esc_url_raw(rest_url('peartree/v1')),
            'trackEndpoint' => esc_url_raw(rest_url('peartree/v1/track')),
            'nonce' => wp_create_nonce('wp_rest'),
            'trackingNamespace' => 'poradnik_pro',
            'experiment' => [
                'conversionHeroV1' => ExperimentService::variant('conversion_hero_v1'),
            ],
            'intentMap' => [
                'high' => ['specjalista', 'kontakt', 'wycena', 'firma', 'lokalnie', 'usluga'],
                'mid' => ['ranking', 'najlepszy', 'porownanie', 'top', 'opinia'],
                'low' => ['jak', 'co to', 'poradnik', 'dlaczego', 'kiedy'],
            ],
            'channelDefaults' => [
                'youtube' => [
                    'utm_source' => 'youtube',
                    'utm_medium' => 'shorts',
                    'utm_campaign' => 'deep_link_distribution',
                ],
                'pinterest' => [
                    'utm_source' => 'pinterest',
                    'utm_medium' => 'pin',
                    'utm_campaign' => 'deep_link_distribution',
                ],
                'discover' => [
                    'utm_source' => 'discover',
                    'utm_medium' => 'organic',
                    'utm_campaign' => 'discover_hooks',
                ],
            ],
        ]);
    }
}
