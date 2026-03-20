(() => {
    const root = document.querySelector('[data-pp-search]');
    if (!root || !window.PoradnikPro) {
        return;
    }

    const input = root.querySelector('input[name="q"]');
    const output = root.querySelector('[data-pp-search-results]');
    let timer;
    const intentMap = window.PoradnikPro.config?.intentMap || {};

    const classifyIntent = (query) => {
        const value = query.toLowerCase();
        const hitCount = (keywords = []) => keywords.reduce((acc, keyword) => acc + (value.includes(keyword) ? 1 : 0), 0);
        const high = hitCount(intentMap.high || []);
        const mid = hitCount(intentMap.mid || []);
        const low = hitCount(intentMap.low || []);

        if (high >= mid && high >= low) {
            return 'high';
        }

        if (mid >= low) {
            return 'mid';
        }

        return 'low';
    };

    const intentHint = (intent) => {
        if (intent === 'high') {
            return '<p><strong>Tryb kontakt:</strong> Zacznij od specjalistow i formularza lead.</p>';
        }

        if (intent === 'mid') {
            return '<p><strong>Tryb porownania:</strong> Najpierw ranking i tabela ofert.</p>';
        }

        return '<p><strong>Tryb edukacyjny:</strong> Najpierw poradniki i FAQ.</p>';
    };

    const renderGroup = (title, items = []) => {
        if (!items.length) {
            return '';
        }

        const entries = items
            .slice(0, 5)
            .map((item) => `<li><a href="${item.url || '#'}">${item.title || ''}</a></li>`)
            .join('');

        return `<section><h4>${title}</h4><ul>${entries}</ul></section>`;
    };

    const runSearch = async () => {
        const query = input.value.trim();
        if (query.length < 2) {
            output.innerHTML = '';
            return;
        }

        try {
            const data = await window.PoradnikPro.request(`/search?q=${encodeURIComponent(query)}`);
            const guidesCount = (data.guides || []).length;
            const specialistsCount = (data.specialists || []).length;
            const rankingsCount = (data.rankings || []).length;
            const intent = classifyIntent(query);
            const routeGroups = intent === 'high'
                ? [
                    renderGroup('Specjalisci', data.specialists),
                    renderGroup('Rankingi', data.rankings),
                    renderGroup('Poradniki', data.guides),
                ]
                : intent === 'mid'
                    ? [
                        renderGroup('Rankingi', data.rankings),
                        renderGroup('Poradniki', data.guides),
                        renderGroup('Specjalisci', data.specialists),
                    ]
                    : [
                        renderGroup('Poradniki', data.guides),
                        renderGroup('Rankingi', data.rankings),
                        renderGroup('Specjalisci', data.specialists),
                    ];

            output.innerHTML = intentHint(intent) + routeGroups.join('');

            window.PoradnikProTracking?.emit('search_query', {
                query,
                queryLength: query.length,
                intent,
                totalResults: guidesCount + specialistsCount + rankingsCount,
                groups: {
                    guides: guidesCount,
                    specialists: specialistsCount,
                    rankings: rankingsCount,
                },
            });
        } catch (error) {
            output.innerHTML = '<p>Nie mozna pobrac wynikow. Sprobuj ponownie.</p>';
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 250);
    });
})();