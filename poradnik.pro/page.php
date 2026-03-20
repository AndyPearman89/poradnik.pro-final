<?php

declare(strict_types=1);

get_header();
?>

<main class="pp-container pp-section">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article class="pp-card">
                <h1><?php the_title(); ?></h1>
                <div class="pp-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p><?php esc_html_e('Brak tresci.', 'poradnik-pro'); ?></p>
    <?php endif; ?>
</main>

<?php
get_footer();
