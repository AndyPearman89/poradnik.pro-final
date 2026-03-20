<?php

declare(strict_types=1);

use PoradnikPro\UiService;

get_header();
?>

<main id="main" class="pp-main">
    <?php get_template_part('template-parts/hero/home-hero'); ?>
    <?php get_template_part('template-parts/sections/home-sections'); ?>

    <section class="pp-section">
        <div class="pp-container">
            <?php get_template_part('template-parts/components/channel-deep-links'); ?>
        </div>
    </section>

    <section class="pp-section">
        <div class="pp-container">
            <?php UiService::renderCta(
                'Potrzebujesz szybkiej pomocy specjalisty?',
                'Wyslij formularz i porownaj odpowiedzi zwrotne od firm z Twojej okolicy.',
                'Przejdz do formularza',
                '#lead-form'
            ); ?>
        </div>
    </section>
</main>

<div class="pp-sticky-cta" data-pp-sticky-cta>
    <a class="pp-btn pp-btn--primary" href="#lead-form">Skontaktuj sie teraz</a>
</div>

<?php
get_footer();
