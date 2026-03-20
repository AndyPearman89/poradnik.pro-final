<?php

declare(strict_types=1);

?>

<section class="pp-hero">
    <div class="pp-container">
        <div class="pp-hero__body">
            <span class="pp-badge pp-badge--free">Problem -> Guide -> Specialist</span>
            <h1>Znajdz najlepsza droge od problemu do konkretnej pomocy</h1>
            <p class="u-text-muted">Szukaj poradnikow, porownuj rozwiazania i wyslij lead do sprawdzonych specjalistow.</p>

            <form class="pp-search" data-pp-search action="<?php echo esc_url(home_url('/')); ?>" method="get">
                <label for="pp-search-input" class="screen-reader-text"><?php esc_html_e('Szukaj poradnikow', 'poradnik-pro'); ?></label>
                <input id="pp-search-input" name="q" type="search" placeholder="Np. jak wybrac fotowoltaike" autocomplete="off">
                <div class="u-flex">
                    <button class="pp-btn pp-btn--primary" type="submit">Szukaj</button>
                    <a class="pp-btn pp-btn--ghost" href="#lead-form">Zapytaj specjaliste</a>
                </div>
                <div data-pp-search-results aria-live="polite"></div>
            </form>
        </div>
    </div>
</section>