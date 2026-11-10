function initializeLegalNavigation() {
    const page = document.querySelector('.legal-page');
    const header = document.querySelector('.public-header');
    if (!page || !header) return;

    const measure = () => page.style.setProperty('--legal-header-offset', `${Math.ceil(header.getBoundingClientRect().height) + 24}px`);
    measure();
    window.addEventListener('resize', measure, { passive: true });
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(measure).observe(header);

    const links = page.querySelectorAll('.legal-sidebar a[href^="#"]');
    const markCurrentSection = () => {
        const current = window.location.hash || links[0]?.getAttribute('href');
        links.forEach(link => {
            if (link.getAttribute('href') === current) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
    };
    markCurrentSection();
    window.addEventListener('hashchange', markCurrentSection);

    page.querySelectorAll('.legal-contents-mobile a').forEach(link => {
        link.addEventListener('click', () => link.closest('details').removeAttribute('open'));
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeLegalNavigation);
else initializeLegalNavigation();
