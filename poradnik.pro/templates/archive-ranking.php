<?php

declare(strict_types=1);

use PoradnikPro\MonetizationService;

get_header();

$offers = MonetizationService::rankedOffers();
$topOffer = $offers[0] ?? null;
?>
<main class="pp-container pp-section">
    <h1><?php esc_html_e('Rankingi', 'poradnik-pro'); ?></h1>
    <?php get_template_part('template-parts/components/affiliate-disclosure'); ?>

    <?php if (have_posts()) : ?>
        <div class="pp-grid pp-grid--2">
            <?php while (have_posts()) : the_post(); ?>
                <article class="pp-card pp-ranking-card">
                    <span class="pp-badge pp-badge--premium">PREMIUM</span>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p class="u-text-muted"><?php echo esc_html(wp_trim_words((string) get_the_excerpt(), 20)); ?></p>
                    <?php if ($topOffer) : ?>
                        <a class="pp-btn pp-btn--secondary" data-pp-affiliate href="<?php echo esc_url((string) ($topOffer['affiliate_url'] ?? '#')); ?>">
                            <?php esc_html_e('Sprawdz najlepsza oferte', 'poradnik-pro'); ?>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</main>
<?php
get_footer();