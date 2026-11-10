function initializeFloatingSocial() {
    const root = document.querySelector('[data-floating-social]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    const compact = root.querySelector('.floating-social-compact');
    const launcher = root.querySelector('[data-social-launcher]');
    const panel = root.querySelector('.floating-social-panel');
    const desktop = matchMedia('(min-width: 1280px)');
    compact.hidden = false;
    function close(restore = false) {
        panel.hidden = true;
        launcher.setAttribute('aria-expanded', 'false');
        if (restore && !desktop.matches) launcher.focus({ preventScroll: true });
    }
    launcher.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
    });
    root.querySelector('[data-social-close]').addEventListener('click', () => close(true));
    panel.addEventListener('click', event => { if (event.target.closest('a')) close(); });
    document.addEventListener('pointerdown', event => { if (!root.contains(event.target)) close(); });
    document.addEventListener('focusin', event => { if (!root.contains(event.target)) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); close(true); } });
    desktop.addEventListener('change', () => close());
    function measure() {
        const contact = document.querySelector('[data-whatsapp-cta][data-context="public"]');
        const header = document.querySelector('.public-header');
        const lower = contact ? Math.max(0, innerHeight - contact.getBoundingClientRect().top + 12) : 20;
        root.style.setProperty('--social-bottom', `${lower}px`);
        root.style.setProperty('--social-top', `${Math.max(16, header?.getBoundingClientRect().bottom ?? 120) + 16}px`);
    }
    window.addEventListener('resize', measure, { passive: true });
    if (document.querySelector('.public-header')) new ResizeObserver(measure).observe(document.querySelector('.public-header'));
    measure();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeFloatingSocial, { once: true });
else initializeFloatingSocial();
