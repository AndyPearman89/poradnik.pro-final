<?php

declare(strict_types=1);

get_header();

$guides = get_posts([
    'post_type' => 'post',
    'posts_per_page' => 3,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
]);
?>
<main class="pp-container pp-section">
    <h1><?php esc_html_e('Specjalisci lokalni', 'poradnik-pro'); ?></h1>
    <p class="u-text-muted"><?php esc_html_e('Lead-first: porownaj specjalistow i wyslij jedno zapytanie do kilku firm.', 'poradnik-pro'); ?></p>

    <?php if (have_posts()) : ?>
        <div class="pp-grid pp-grid--3">
            <?php while (have_posts()) : the_post(); ?>
                <article class="pp-card">
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <p><?php echo esc_html(wp_trim_words((string) get_the_excerpt(), 18)); ?></p>
                    <a class="pp-btn pp-btn--secondary" href="<?php the_permalink(); ?>"><?php esc_html_e('Zobacz profil', 'poradnik-pro'); ?></a>
                </article>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <section class="pp-section">
        <div class="pp-grid pp-grid--2">
            <article class="pp-card">
                <h2><?php esc_html_e('Powiazane poradniki', 'poradnik-pro'); ?></h2>
                <ul>
                    <?php foreach ($guides as $guide) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink($guide)); ?>"><?php echo esc_html(get_the_title($guide)); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="pp-card">
                <h2><?php esc_html_e('Powiazane rankingi', 'poradnik-pro'); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/ranking/')); ?>"><?php esc_html_e('Ranking uslug i wykonawcow', 'poradnik-pro'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/ranking/top-3/')); ?>"><?php esc_html_e('TOP 3 rekomendacje', 'poradnik-pro'); ?></a></li>
                </ul>
            </article>
        </div>
    </section>
</main>
<?php
get_footer();