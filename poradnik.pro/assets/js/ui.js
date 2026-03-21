(() => {
    const experimentVariant = window.PoradnikPro?.config?.experiment?.conversionHeroV1 || 'A';
    const experimentName = 'conversion_hero_v1';

    const stickyCta = document.querySelector('[data-pp-sticky-cta]');
    const ctaButton = stickyCta?.querySelector('a, button');
    if (ctaButton) {
        ctaButton.addEventListener('click', () => {
            window.PoradnikProTracking?.emit('cta_click', {
                source: 'sticky_mobile',
                label: ctaButton.textContent?.trim() || 'cta',
                variant: experimentVariant,
                experiment: experimentName,
            });
        });
    }

    const injectInlineCta = () => {
        const contentRoot = document.querySelector('.entry-content, .pp-main');
        if (!contentRoot) {
            return;
        }

        const sections = Array.from(contentRoot.querySelectorAll('p, h2, h3'));
        if (sections.length < 6) {
            return;
        }

        let inserted = 0;
        sections.forEach((section, index) => {
            if ((index + 1) % 6 !== 0 || inserted >= 2) {
                return;
            }

            const cta = document.createElement('div');
            cta.className = 'pp-inline-cta pp-card';

            const headline = experimentVariant === 'B'
                ? 'Porownaj oferty i oszczedz czas'
                : 'Potrzebujesz konkretnej wyceny?';
            const description = experimentVariant === 'B'
                ? 'Jeden formularz, kilka odpowiedzi i szybsza decyzja zakupowa.'
                : 'Wyslij krotki formularz i porownaj odpowiedzi specjalistow.';
            const ctaLabel = experimentVariant === 'B'
                ? 'Porownaj odpowiedzi'
                : 'Przejdz do formularza';

            cta.innerHTML =
                `<h3>${headline}</h3>` +
                `<p>${description}</p>` +
                `<a class="pp-btn pp-btn--primary" href="#lead-form" data-pp-inline-cta>${ctaLabel}</a>`;

            section.parentNode?.insertBefore(cta, section.nextSibling);
            inserted += 1;
        });

        contentRoot.querySelectorAll('[data-pp-inline-cta]').forEach((link) => {
            link.addEventListener('click', () => {
                window.PoradnikProTracking?.emit('cta_click', {
                    source: 'inline_injection',
                    label: link.textContent?.trim() || 'inline_cta',
                    variant: experimentVariant,
                    experiment: experimentName,
                });
            });
        });
    };

    injectInlineCta();

    document.querySelectorAll('[data-pp-affiliate]').forEach((link) => {
        link.addEventListener('click', () => {
            const mode = link.getAttribute('data-pp-affiliate-mode') || 'direct';
            window.PoradnikProTracking?.emit('cta_click', {
                source: mode === 'fallback' ? 'affiliate_fallback_lead' : 'affiliate',
                mode,
                label: link.textContent?.trim() || 'affiliate_cta',
                href: link.getAttribute('href') || '',
                variant: experimentVariant,
                experiment: experimentName,
            });
        });
    });

    document.querySelectorAll('[data-pp-channel-link]').forEach((link) => {
        link.addEventListener('click', () => {
            window.PoradnikProTracking?.emit('channel_deeplink_click', {
                channel: link.getAttribute('data-pp-channel') || 'unknown',
                href: link.getAttribute('href') || '',
                label: link.textContent?.trim() || 'channel_link',
            });
        });
    });

    let lastMilestone = 0;
    document.addEventListener('scroll', () => {
        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (scrollHeight <= 0) {
            return;
        }

        const ratio = Math.floor((window.scrollY / scrollHeight) * 100);
        const currentMilestone = Math.floor(ratio / 25) * 25;

        if (currentMilestone >= 25 && currentMilestone !== lastMilestone) {
            lastMilestone = currentMilestone;
            window.PoradnikProTracking?.emit('scroll_depth', {
                percent: currentMilestone,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight,
                },
            });
        }
    }, { passive: true });
})();