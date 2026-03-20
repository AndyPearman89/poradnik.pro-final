(() => {
    const config = window.poradnikProConfig || {};

    const request = async (path, options = {}) => {
        const timeoutMs = Number(options.timeoutMs || config.requestTimeoutMs || 8000);
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), timeoutMs);

        const response = await fetch(`${config.apiBase}${path}`, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce || '',
            },
            signal: controller.signal,
            ...options,
        });
        clearTimeout(timeout);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    };

    window.PoradnikPro = {
        config,
        request,
    };
})();