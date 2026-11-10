function initializeStudentShell() {
    const root = document.querySelector('.student-portal');
    if (!root || root.dataset.shellReady) return;
    root.dataset.shellReady = 'true';
    const bottomNav = root.querySelector('[data-student-bottom-nav]');
    const more = root.querySelector('[data-student-more]');
    const dialog = root.querySelector('#student-more-dialog');
    const account = root.querySelector('[data-student-account]');
    const contact = root.querySelector('[data-student-floating-tools]');
    const desktop = window.matchMedia('(min-width: 1024px)');
    let previousOverflow = '';
    let savedScroll = 0;
    let backdropStart = false;
    let frame = 0;
    let largestHeight = window.innerHeight;

    function measureNavigation() {
        const navigationHeight = bottomNav && !desktop.matches ? bottomNav.getBoundingClientRect().height : 0;
        const inlineNavigation = navigationHeight > window.innerHeight / 2;
        root.toggleAttribute('data-student-inline-navigation', inlineNavigation);
        root.style.setProperty('--student-nav-height', `${inlineNavigation ? 0 : navigationHeight}px`);
        root.style.setProperty('--student-contact-height', contact ? `${contact.getBoundingClientRect().height + 24}px` : '0px');
    }
    function unlock() {
        if (more?.getAttribute('aria-expanded') !== 'true') return;
        document.documentElement.style.overflow = previousOverflow;
        more.setAttribute('aria-expanded', 'false');
        window.scrollTo({ top: savedScroll, behavior: 'instant' });
        if (!desktop.matches) more.focus({ preventScroll: true });
    }
    function closeMore() {
        if (dialog?.open) dialog.close();
        unlock();
    }
    more?.addEventListener('click', () => {
        if (desktop.matches || dialog.open) return;
        document.dispatchEvent(new Event('student:more-opening'));
        savedScroll = window.scrollY;
        previousOverflow = document.documentElement.style.overflow;
        dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
        more.setAttribute('aria-expanded', 'true');
        dialog.querySelector('[data-student-more-close]').focus({ preventScroll: true });
    });
    dialog?.querySelector('[data-student-more-close]').addEventListener('click', closeMore);
    dialog?.addEventListener('close', unlock);
    dialog?.addEventListener('pointerdown', event => { backdropStart = event.target === dialog; });
    dialog?.addEventListener('click', event => {
        if (!backdropStart || event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeMore();
    });
    dialog?.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('a[href], button, input:not([type="hidden"]), [tabindex="0"]')].filter(element => element.getClientRects().length && !element.disabled);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    account?.addEventListener('keydown', event => {
        if (event.key === 'Escape' && account.open) { account.open = false; account.querySelector('summary').focus(); }
    });
    document.addEventListener('click', event => { if (account?.open && !account.contains(event.target)) account.open = false; });
    document.addEventListener('focusin', event => { if (account?.open && !account.contains(event.target)) account.open = false; });
    desktop.addEventListener('change', () => {
        closeMore();
        if (account) account.open = false;
        root.removeAttribute('data-student-keyboard');
        measureNavigation();
    });
    window.addEventListener('pagehide', closeMore);
    window.addEventListener('pageshow', () => { closeMore(); measureNavigation(); });
    if (bottomNav) new ResizeObserver(measureNavigation).observe(bottomNav);
    if (contact) new ResizeObserver(measureNavigation).observe(contact);

    function updateClearance() {
        frame = 0;
        const viewport = window.visualViewport;
        const editing = document.activeElement?.matches('input:not([type="checkbox"]):not([type="radio"]), textarea, select, [contenteditable="true"]');
        if (!editing) largestHeight = window.innerHeight;
        const keyboard = !desktop.matches && editing && viewport?.scale === 1 && largestHeight - viewport.height > 150;
        root.toggleAttribute('data-student-keyboard', Boolean(keyboard));
        if (contact) {
            const rect = contact.getBoundingClientRect();
            const obstructing = [...root.querySelectorAll('main :is(input, select, textarea, button, a, summary, video, iframe, [role="alert"])')].some(element => {
                if (element.matches('a') && element !== document.activeElement && !element.matches('[data-cta], .student-button')) return false;
                const bounds = element.getBoundingClientRect();
                return bounds.width && bounds.height && bounds.left < rect.right + 8 && bounds.right > rect.left - 8 && bounds.top < rect.bottom + 8 && bounds.bottom > rect.top - 8;
            });
            contact.toggleAttribute('data-student-obstructing', obstructing);
        }
    }
    function scheduleClearance() { if (!frame) frame = requestAnimationFrame(updateClearance); }
    window.addEventListener('scroll', scheduleClearance, { passive: true });
    window.addEventListener('resize', () => { measureNavigation(); scheduleClearance(); }, { passive: true });
    window.visualViewport?.addEventListener('resize', scheduleClearance, { passive: true });
    root.addEventListener('focusin', event => {
        if (contact?.contains(event.target)) contact.scrollIntoView({ block: 'end', behavior: 'instant' });
        scheduleClearance();
    });
    root.addEventListener('focusout', scheduleClearance);
    function syncHigherInterface() {
        const modal = [...document.querySelectorAll('dialog:modal, [aria-modal="true"]:not(dialog)')].some(element => element.getClientRects().length && element.getAttribute('aria-hidden') !== 'true' && getComputedStyle(element).visibility !== 'hidden');
        root.toggleAttribute('data-student-higher-interface', Boolean(document.fullscreenElement || modal));
        scheduleClearance();
    }
    document.addEventListener('fullscreenchange', syncHigherInterface);
    new MutationObserver(syncHigherInterface).observe(root, { subtree: true, attributes: true, attributeFilter: ['open', 'aria-modal', 'hidden', 'aria-hidden', 'style'] });
    measureNavigation();
    scheduleClearance();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeStudentShell, { once: true });
else initializeStudentShell();
