import { uiText } from './ui-translations.js';
function initializeStudentAuthentication() {
    document.querySelectorAll('[data-student-auth-screen]').forEach(screen => {
        if (screen.dataset.authInitialized) return;
        screen.dataset.authInitialized = 'true';
        const summary = screen.querySelector('[data-student-error-summary]');
        if (summary) requestAnimationFrame(() => summary.focus());

        const form = screen.querySelector('[data-student-sign-in]');
        if (!form) return;
        const button = form.querySelector('[data-student-sign-in-submit]');
        const group = form.querySelector('[data-student-identity-group]');
        const insufficient = form.querySelector('[data-student-insufficient]');
        const count = () => ['name', 'email', 'phone'].filter(name => form.elements.namedItem(name).value.trim()).length;
        const restore = () => {
            form.setAttribute('aria-busy', 'false');
            button.disabled = false;
            button.textContent = uiText('Sign In');
        };
        restore();
        window.addEventListener('pageshow', restore);
        form.addEventListener('input', () => {
            if (count() >= 2) {
                insufficient.hidden = true;
                group.setAttribute('aria-describedby', 'identity-instructions');
            }
        });
        form.addEventListener('submit', event => {
            if (form.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            if (!form.checkValidity()) {
                event.preventDefault();
                return;
            }
            if (count() < 2) {
                event.preventDefault();
                insufficient.hidden = false;
                group.setAttribute('aria-describedby', 'identity-instructions sign-in-insufficient');
                insufficient.focus();
                return;
            }
            form.setAttribute('aria-busy', 'true');
            button.disabled = true;
            button.textContent = uiText('Signing In…');
        });
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeStudentAuthentication);
else initializeStudentAuthentication();
