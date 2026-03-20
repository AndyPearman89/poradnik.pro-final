<?php

declare(strict_types=1);

namespace PoradnikPro;

final class UiService
{
    public static function renderCta(string $title, string $description, string $buttonLabel, string $buttonUrl): void
    {
        set_query_var('cta_title', $title);
        set_query_var('cta_description', $description);
        set_query_var('cta_button_label', $buttonLabel);
        set_query_var('cta_button_url', $buttonUrl);

        get_template_part('template-parts/components/cta-block');
    }
}
