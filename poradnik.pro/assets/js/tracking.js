(() => {
    const namespace = window.poradnikProConfig?.trackingNamespace || 'poradnik_pro';
    const trackEndpoint = window.poradnikProConfig?.trackEndpoint || '';

    const pushToDataLayer = (payload) => {
        if (!Array.isArray(window.dataLayer)) {
            return;
        }

        window.dataLayer.push(payload);
    };

    const emit = (eventName, payload = {}) => {
        const eventPayload = {
            event: `${namespace}:${eventName}`,
            eventName,
            payload,
            ts: Date.now(),
            path: window.location.pathname,
        };

        pushToDataLayer(eventPayload);

        if (trackEndpoint) {
            const serialized = JSON.stringify(eventPayload);
            const blob = new Blob([serialized], { type: 'application/json' });

            if (navigator.sendBeacon) {
                navigator.sendBeacon(trackEndpoint, blob);
            } else {
                fetch(trackEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: serialized,
                    keepalive: true,
                }).catch(() => {});
            }
        }

        window.dispatchEvent(
            new CustomEvent('poradnik-pro:track', {
                detail: eventPayload,
            })
        );
    };

    window.PoradnikProTracking = { emit };
})();