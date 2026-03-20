<?php

declare(strict_types=1);

$title = (string) get_query_var('cta_title', __('Szybki kontakt ze specjalista', 'poradnik-pro'));
$description = (string) get_query_var('cta_description', __('Wyslij lead i porownaj oferty.', 'poradnik-pro'));
$buttonLabel = (string) get_query_var('cta_button_label', __('Skontaktuj sie', 'poradnik-pro'));
$buttonUrl = (string) get_query_var('cta_button_url', '#lead-form');

?>

<div class="pp-cta">
    <h2><?php echo esc_html($title); ?></h2>
    <p><?php echo esc_html($description); ?></p>
    <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url($buttonUrl); ?>"><?php echo esc_html($buttonLabel); ?></a>
</div>