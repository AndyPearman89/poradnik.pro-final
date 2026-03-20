<?php

declare(strict_types=1);

get_header();
?>
<main class="pp-container pp-section">
    <h1><?php the_title(); ?></h1>
    <?php the_content(); ?>

    <section class="pp-section">
        <?php get_template_part('template-parts/components/channel-deep-links'); ?>
    </section>
</main>
<?php
get_footer();