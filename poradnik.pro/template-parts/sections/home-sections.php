<?php

declare(strict_types=1);

use PoradnikPro\ApiClient;

$guides = ApiClient::get('/guides');
$rankings = ApiClient::get('/rankings');
$specialists = ApiClient::get('/listings');

$guideItems = $guides['ok'] ? (array) ($guides['data']['items'] ?? $guides['data']) : [];
$rankingItems = $rankings['ok'] ? (array) ($rankings['data']['items'] ?? $rankings['data']) : [];
$specialistItems = $specialists['ok'] ? (array) ($specialists['data']['items'] ?? $specialists['data']) : [];

if ($guideItems === []) {
    $guideItems = [
        ['title' => 'Jak wybrac wykonawce uslugi'],
        ['title' => 'Ile to kosztuje i jak porownywac oferty'],
        ['title' => 'Najczestsze bledy przy wyborze specjalisty'],
    ];
}

if ($rankingItems === []) {
    $rankingItems = [
        ['title' => 'Top 3 rozwiazania dla Twojego problemu', 'badge' => 'PREMIUM'],
        ['title' => 'Ranking narzedzi i ofert miesieca', 'badge' => 'PREMIUM+'],
    ];
}

if ($specialistItems === []) {
    $specialistItems = [
        ['title' => 'Specjalista lokalny #1'],
        ['title' => 'Specjalista lokalny #2'],
        ['title' => 'Specjalista lokalny #3'],
    ];
}

?>

<section class="pp-section">
    <div class="pp-container">
        <h2>Popularne poradniki</h2>
        <div class="pp-grid pp-grid--3">
            <?php foreach (array_slice($guideItems, 0, 6) as $guide) : ?>
                <article class="pp-card">
                    <h3><?php echo esc_html((string) ($guide['title'] ?? __('Poradnik', 'poradnik-pro'))); ?></h3>
                    <a class="pp-btn pp-btn--secondary" href="<?php echo esc_url((string) ($guide['url'] ?? '#')); ?>">Czytaj</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="pp-section">
    <div class="pp-container">
        <h2>Rankingi i porownania</h2>
        <div class="pp-grid pp-grid--2">
            <?php foreach (array_slice($rankingItems, 0, 4) as $ranking) : ?>
                <article class="pp-card">
                    <span class="pp-badge pp-badge--premium"><?php echo esc_html((string) ($ranking['badge'] ?? 'PREMIUM')); ?></span>
                    <h3 class="u-mt-4"><?php echo esc_html((string) ($ranking['title'] ?? __('Ranking', 'poradnik-pro'))); ?></h3>
                    <a class="pp-btn pp-btn--ghost" href="<?php echo esc_url((string) ($ranking['url'] ?? '#')); ?>">Zobacz ranking</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="pp-section" id="lead-form">
    <div class="pp-container">
        <h2>Zapytaj specjaliste</h2>
        <?php get_template_part('template-parts/components/trust-block'); ?>
        <div class="u-mt-4"></div>
        <?php get_template_part('template-parts/components/urgency-block'); ?>
        <div class="pp-grid pp-grid--2">
            <div class="pp-card">
                <h3>Sprawdzeni lokalni eksperci</h3>
                <ul>
                    <?php foreach (array_slice($specialistItems, 0, 5) as $specialist) : ?>
                        <li><?php echo esc_html((string) ($specialist['title'] ?? __('Specjalista', 'poradnik-pro'))); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <form class="pp-card pp-form" data-pp-lead-form>
                <label for="lead-name"><?php esc_html_e('Imie i nazwisko', 'poradnik-pro'); ?></label>
                <input id="lead-name" name="name" required>

                <label for="lead-contact"><?php esc_html_e('Email lub telefon', 'poradnik-pro'); ?></label>
                <input id="lead-contact" name="email_or_phone" required>

                <label for="lead-problem"><?php esc_html_e('Problem', 'poradnik-pro'); ?></label>
                <textarea id="lead-problem" name="problem" required></textarea>

                <label for="lead-location"><?php esc_html_e('Lokalizacja', 'poradnik-pro'); ?></label>
                <input id="lead-location" name="location" required>

                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;">

                <button class="pp-btn pp-btn--primary" type="submit">Wyslij zapytanie</button>
                <p class="u-text-muted" data-pp-form-status aria-live="polite"></p>
            </form>
        </div>
    </div>
</section>