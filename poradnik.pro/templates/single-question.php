<?php

declare(strict_types=1);

get_header();

$rawAnswers = (string) get_post_meta(get_the_ID(), 'pp_answers', true);
$answers = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawAnswers))));

if ($answers === []) {
	$answers = [
		__('To zalezy od zakresu problemu i lokalizacji, ale warto porownac kilka opcji.', 'poradnik-pro'),
		__('Sprawdz rankingi i opinie, a potem wyslij krotki formularz do specjalistow.', 'poradnik-pro'),
	];
}
?>

<main class="pp-container pp-section">
	<article class="pp-card">
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>

	<section class="pp-section">
		<h2><?php esc_html_e('Odpowiedzi', 'poradnik-pro'); ?></h2>
		<div class="pp-grid">
			<?php foreach ($answers as $answer) : ?>
				<article class="pp-card">
					<p><?php echo esc_html($answer); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="pp-card">
		<h2><?php esc_html_e('Potrzebujesz indywidualnej pomocy?', 'poradnik-pro'); ?></h2>
		<p><?php esc_html_e('Przejdz do lokalnych specjalistow lub porownaj rankingi rozwiazan.', 'poradnik-pro'); ?></p>
		<div class="u-flex">
			<a class="pp-btn pp-btn--primary" href="<?php echo esc_url(home_url('/uslugi/')); ?>"><?php esc_html_e('Znajdz specjaliste', 'poradnik-pro'); ?></a>
			<a class="pp-btn pp-btn--ghost" href="<?php echo esc_url(home_url('/ranking/')); ?>"><?php esc_html_e('Zobacz ranking', 'poradnik-pro'); ?></a>
		</div>
	</section>
</main>

<?php
get_footer();