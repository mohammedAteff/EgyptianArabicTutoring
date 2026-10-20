export function filterQuickDestinations(query, destinations) {
    const term = query.trim().toLocaleLowerCase();
    for (const destination of destinations) {
        destination.hidden = !destination.textContent.toLocaleLowerCase().includes(term);
    }
}

if (typeof document !== 'undefined') {
    let opener;
    let searchTimer;
    let searchAbort;
    const dialog = () => document.getElementById('staff-quick-actions');
    const open = (button) => {
        const panel = dialog();
        if (!panel || panel.open) return;
        opener = button || document.activeElement;
        window.dispatchEvent(new Event('staff-quick-actions-open'));
        panel.showModal();
    };
    document.addEventListener('click', event => {
        if (event.target.closest('[data-open-quick-actions]')) open(event.target.closest('[data-open-quick-actions]'));
        if (event.target.closest('[data-close-quick-actions]')) dialog()?.close();
    });
    document.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            open();
        }
    });
    document.addEventListener('input', event => {
        if (event.target.id !== 'quick-action-query') return;
        const panel = dialog();
        const term = event.target.value.trim();
        filterQuickDestinations(term, panel.querySelectorAll('[data-quick-destination]'));
        clearTimeout(searchTimer);
        searchAbort?.abort();
        const results = panel.querySelector('[data-quick-search-results]');
        const status = panel.querySelector('[data-quick-search-status]');
        results.replaceChildren();
        status.textContent = '';
        if (term.length < 2) return;
        searchTimer = setTimeout(async () => {
            const abort = new AbortController();
            searchAbort = abort;
            status.textContent = 'Searching authorized records…';
            try {
                const url = new URL(panel.dataset.searchUrl, location.origin);
                url.searchParams.set('q', term);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: abort.signal, cache: 'no-store' });
                if (!response.ok) throw new Error('Search unavailable');
                const data = await response.json();
                if (abort.signal.aborted) return;
                status.textContent = data.items.length ? '' : 'No matching records.';
                for (const item of data.items) {
                    const row = document.createElement('article');
                    row.className = 'rounded-xl border border-slate-200 p-3';
                    const link = document.createElement('a');
                    link.href = item.url;
                    link.className = 'block text-sm font-semibold text-amber-800 underline';
                    link.textContent = item.title;
                    const detail = document.createElement('p');
                    detail.className = 'mt-1 text-xs text-slate-500';
                    detail.textContent = `${item.kind.replaceAll('_', ' ')} · ${item.detail}`;
                    row.append(link, detail);
                    for (const action of item.actions || []) {
                        const button = document.createElement('a');
                        button.href = action.url;
                        button.className = 'mr-3 inline-flex min-h-11 items-center text-xs font-semibold text-amber-800';
                        button.textContent = action.label;
                        row.append(button);
                    }
                    const copies = { email: item.email, phone: item.phone, 'WhatsApp link': item.whatsapp_url };
                    for (const [field, value] of Object.entries(copies)) {
                        if (!value) continue;
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.dataset.copyText = value;
                        button.className = 'mr-3 min-h-11 text-xs font-semibold text-amber-800';
                        button.textContent = `Copy ${field}`;
                        row.append(button);
                    }
                    results.append(row);
                }
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = 'Search is unavailable. Open the search page to try again.';
            }
        }, 250);
    });
    dialog()?.addEventListener('close', () => {
        clearTimeout(searchTimer);
        searchAbort?.abort();
        const rect = opener?.getBoundingClientRect();
        const visible = rect?.width > 0 && rect.right > 0 && rect.bottom > 0 && rect.left < innerWidth && rect.top < innerHeight;
        const target = visible ? opener : document.querySelector('[data-open-quick-actions]');
        target?.focus();
    });
}
