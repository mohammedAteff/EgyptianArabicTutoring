export function filterQuickDestinations(query, destinations) {
    const term = query.trim().toLocaleLowerCase();
    for (const destination of destinations) {
        destination.hidden = !destination.textContent.toLocaleLowerCase().includes(term);
    }
}

if (typeof document !== 'undefined') {
    let opener;
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
        if (event.target.id === 'quick-action-query') filterQuickDestinations(event.target.value, dialog().querySelectorAll('[data-quick-destination]'));
    });
    dialog()?.addEventListener('close', () => {
        const rect = opener?.getBoundingClientRect();
        const visible = rect?.width > 0 && rect.right > 0 && rect.bottom > 0 && rect.left < innerWidth && rect.top < innerHeight;
        const target = visible ? opener : document.querySelector('[data-open-quick-actions]');
        target?.focus();
    });
}
