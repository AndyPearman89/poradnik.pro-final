<?php

declare(strict_types=1);

use PoradnikPro\MonetizationService;
use PoradnikPro\UiService;

get_header();

$offers = MonetizationService::rankedOffers((int) get_the_ID());
$topOffers = array_slice($offers, 0, 3);
?>

<main class="pp-container pp-section">
	<article class="pp-card">
		<h1><?php the_title(); ?></h1>
		<?php the_content(); ?>
	</article>

	<?php get_template_part('template-parts/components/affiliate-disclosure'); ?>

	<section class="pp-section">
		<h2><?php esc_html_e('Top 3 rekomendacje', 'poradnik-pro'); ?></h2>
		<div class="pp-grid pp-grid--3">
			<?php foreach ($topOffers as $offer) : ?>
				<?php $offerCta = MonetizationService::resolveOfferCta((array) $offer); ?>
				<article class="pp-card pp-ranking-card">
					<span class="pp-badge pp-badge--premium"><?php echo esc_html((string) ($offer['badge'] ?? 'PREMIUM')); ?></span>
					<h3 class="u-mt-4"><?php echo esc_html((string) ($offer['name'] ?? 'Oferta')); ?></h3>
					<p class="u-text-muted"><?php echo esc_html((string) ($offer['price'] ?? '')); ?></p>
					<p><?php echo esc_html__('Ocena', 'poradnik-pro') . ': ' . esc_html((string) ($offer['rating'] ?? '-')); ?></p>
					<a class="pp-btn pp-btn--primary" data-pp-affiliate data-pp-affiliate-mode="<?php echo esc_attr(($offerCta['is_fallback'] ?? false) ? 'fallback' : 'direct'); ?>" href="<?php echo esc_url((string) ($offerCta['url'] ?? '#')); ?>">
						<?php esc_html_e('Przejdz do oferty', 'poradnik-pro'); ?>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="pp-section">
		<h2><?php esc_html_e('Tabela porownawcza', 'poradnik-pro'); ?></h2>
		<div class="pp-table-wrap pp-card">
			<table class="pp-compare-table">
				<thead>
				<tr>
					<th><?php esc_html_e('#', 'poradnik-pro'); ?></th>
					<th><?php esc_html_e('Oferta', 'poradnik-pro'); ?></th>
					<th><?php esc_html_e('Ocena', 'poradnik-pro'); ?></th>
					<th><?php esc_html_e('Cena', 'poradnik-pro'); ?></th>
					<th><?php esc_html_e('Akcja', 'poradnik-pro'); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($offers as $offer) : ?>
					<?php $offerCta = MonetizationService::resolveOfferCta((array) $offer); ?>
					<tr>
						<td><?php echo esc_html((string) ($offer['rank'] ?? '')); ?></td>
						<td><?php echo esc_html((string) ($offer['name'] ?? '')); ?></td>
						<td><?php echo esc_html((string) ($offer['rating'] ?? '')); ?></td>
						<td><?php echo esc_html((string) ($offer['price'] ?? '')); ?></td>
						<td>
							<a data-pp-affiliate data-pp-affiliate-mode="<?php echo esc_attr(($offerCta['is_fallback'] ?? false) ? 'fallback' : 'direct'); ?>" href="<?php echo esc_url((string) ($offerCta['url'] ?? '#')); ?>">
								<?php esc_html_e('Szczegoly', 'poradnik-pro'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="pp-section">
		<?php UiService::renderCta(
			__('Chcesz porownac oferty z lokalnymi wykonawcami?', 'poradnik-pro'),
			__('Jesli ranking nie rozwiewa wszystkich watpliwosci, wyslij lead i otrzymaj dopasowane odpowiedzi.', 'poradnik-pro'),
			__('Wyslij zapytanie do specjalistow', 'poradnik-pro'),
			home_url('/uslugi/')
		); ?>
	</section>
</main>

<?php
get_footer();