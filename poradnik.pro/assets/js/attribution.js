(() => {
    const query = new URLSearchParams(window.location.search);
    const payload = {
        source: query.get('utm_source') || 'direct',
        medium: query.get('utm_medium') || 'none',
        campaign: query.get('utm_campaign') || 'none',
        referrer: document.referrer || 'none',
        landingPath: window.location.pathname,
    };

    window.PoradnikProTracking?.emit('attribution_landing', payload);
})();
