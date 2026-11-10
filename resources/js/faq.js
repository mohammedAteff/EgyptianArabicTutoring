function initializeFaqNavigation() {
    const page = document.querySelector('.studio-faq-page .faq-page');
    const header = document.querySelector('.public-header');
    if (!page || !header) return;

    const index = page.querySelector('.faq-topic-index');
    const content = page.closest('main') ?? page;
    const desktop = window.matchMedia('(min-width: 1100px)');
    let frame;

    const update = () => {
        const offset = Math.ceil(header.getBoundingClientRect().height) + 24;
        content.style.setProperty('--faq-header-offset', `${offset}px`);
        if (index) {
            const fits = desktop.matches && index.getBoundingClientRect().height + offset + 24 <= window.innerHeight;
            index.classList.toggle('is-sticky', fits);
        }
    };
    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(update);
    };

    update();
    window.addEventListener('resize', schedule, { passive: true });
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(schedule);
        observer.observe(header);
        if (index) observer.observe(index);
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeFaqNavigation);
else initializeFaqNavigation();
