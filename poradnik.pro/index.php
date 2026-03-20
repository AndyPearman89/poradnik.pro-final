<?php

declare(strict_types=1);

get_header();
?>

<main class="pp-container pp-section">
    <?php if (have_posts()) : ?>
        <div class="pp-grid pp-grid--2">
            <?php while (have_posts()) : the_post(); ?>
                <article class="pp-card">
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php the_excerpt(); ?>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('Brak tresci.', 'poradnik-pro'); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();