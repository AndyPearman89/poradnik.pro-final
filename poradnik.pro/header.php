<?php

declare(strict_types=1);

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="pp-skip-link" href="#main"><?php esc_html_e('Przejdz do tresci', 'poradnik-pro'); ?></a>
<header class="pp-section">
    <div class="pp-container u-flex" style="justify-content:space-between;">
        <a href="<?php echo esc_url(home_url('/')); ?>"><strong><?php bloginfo('name'); ?></strong></a>
        <nav aria-label="<?php esc_attr_e('Nawigacja glowna', 'poradnik-pro'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'fallback_cb' => false,
            ]);
            ?>
        </nav>
    </div>
</header>