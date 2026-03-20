<?php

declare(strict_types=1);

namespace PoradnikPro;

final class InternalLinkingService
{
    public static function appendRelatedLinks(string $content): string
    {
        if (! is_singular() || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        $relatedGuides = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
            'post_status' => 'publish',
            'post__not_in' => [get_the_ID()],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $links = [];
        foreach ($relatedGuides as $post) {
            $links[] = sprintf(
                '<li><a href="%s">%s</a></li>',
                esc_url(get_permalink($post)),
                esc_html(get_the_title($post))
            );
        }

        $links[] = sprintf(
            '<li><a href="%s">%s</a></li>',
            esc_url(home_url('/ranking/')),
            esc_html__('Zobacz powiazane rankingi', 'poradnik-pro')
        );

        $links[] = sprintf(
            '<li><a href="%s">%s</a></li>',
            esc_url(home_url('/uslugi/')),
            esc_html__('Znajdz lokalnych specjalistow', 'poradnik-pro')
        );

        $block = '<section class="pp-card u-mt-6">';
        $block .= '<h3>' . esc_html__('Powiazane tresci', 'poradnik-pro') . '</h3>';
        $block .= '<ul>' . implode('', $links) . '</ul>';
        $block .= '</section>';

        return $content . $block;
    }
}