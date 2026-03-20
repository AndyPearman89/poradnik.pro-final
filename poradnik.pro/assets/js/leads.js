(() => {
    const form = document.querySelector('[data-pp-lead-form]');
    if (!form || !window.PoradnikPro) {
        return;
    }

    const status = form.querySelector('[data-pp-form-status]');
    const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const validate = (payload) => {
        const required = ['name', 'email_or_phone', 'problem', 'location'];
        return required.every((field) => String(payload[field] || '').trim().length > 0);
    };

    const submitLead = async (payload, retries = 2, attempt = 1) => {
        try {
            const response = await window.PoradnikPro.request('/leads', {
                method: 'POST',
                body: JSON.stringify(payload),
                timeoutMs: 9000,
            });

            return { response, attempt };
        } catch (error) {
            if (retries > 0) {
                await wait(350 * attempt);
                return submitLead(payload, retries - 1, attempt + 1);
            }

            throw error;
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = {
            name: form.name.value,
            email_or_phone: form.email_or_phone.value,
            problem: form.problem.value,
            location: form.location.value,
            website: form.website?.value || '',
        };

        if (payload.website.trim() !== '') {
            status.textContent = 'Dziekujemy.';
            return;
        }

        if (!validate(payload)) {
            status.textContent = 'Uzupelnij wszystkie wymagane pola.';
            window.PoradnikProTracking?.emit('lead_submit_failure', {
                reason: 'validation_failed',
            });
            return;
        }

        status.textContent = 'Wysylanie...';

        try {
            const { attempt } = await submitLead(payload);
            status.textContent = 'Dziekujemy. Kontakt zostal wyslany.';
            window.PoradnikProTracking?.emit('lead_submit_success', {
                attempt,
                hasLocation: payload.location.trim().length > 0,
            });
            form.reset();
        } catch (error) {
            status.textContent = 'Nie udalo sie wyslac formularza. Sprobuj ponownie.';
            window.PoradnikProTracking?.emit('lead_submit_failure', {
                reason: error?.name === 'AbortError' ? 'timeout' : 'request_failed',
            });
        }
    });
})();