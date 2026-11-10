function initializeCairoClocks() {
    const clocks = document.querySelectorAll('[data-cairo-clock]');
    if (!clocks.length) return;

    const locale = document.documentElement.dataset.uiLocale || document.documentElement.lang || 'en';
    const formatter = new Intl.DateTimeFormat(locale, {
        timeZone: 'Africa/Cairo',
        hour: 'numeric',
        minute: '2-digit',
        hour12: locale === 'en',
    });

    const update = () => {
        const now = new Date();
        for (const clock of clocks) {
            clock.textContent = formatter.format(now);
            clock.dateTime = now.toISOString();
        }
    };

    update();
    window.setInterval(update, 30000);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeCairoClocks);
else initializeCairoClocks();
