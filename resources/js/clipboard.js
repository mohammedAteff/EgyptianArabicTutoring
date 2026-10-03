export function studentDetailValue(form, name) {
    const field = form.elements.namedItem(name);
    let value = field?.value?.trim() || '';
    if (name === 'date_of_birth' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        value = value.split('-').reverse().join('/');
    }
    if (name === 'phone' && value && !value.startsWith('+')) {
        const country = form.elements.namedItem('phone_country')?.value?.trim();
        if (country) value = `${country.toUpperCase()} ${value}`;
    }
    if (name === 'identity_status' && field?.selectedOptions) value = field.selectedOptions[0]?.textContent.trim() || value;
    return value;
}

export function studentDetails(form) {
    const fields = { first_name: 'First name', last_name: 'Last name', email: 'Email', phone: 'Phone', date_of_birth: 'Date of birth', identity_status: 'Identity status', preferred_timezone: 'Timezone', internal_notes: 'Internal notes' };
    return Object.entries(fields).map(([name, label]) => `${label}: ${studentDetailValue(form, name) || '—'}`).join('\n');
}

async function copyText(value) {
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        return;
    }
    const input = document.createElement('textarea');
    input.value = value;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.append(input);
    input.select();
    const copied = document.execCommand('copy');
    input.remove();
    if (!copied) throw new Error('Clipboard unavailable');
}

if (typeof document !== 'undefined') document.addEventListener('click', async event => {
    const button = event.target.closest('[data-copy-field], [data-copy-student], [data-copy-bin]');
    if (!button) return;
    const form = document.querySelector('[data-student-identity]');
    const value = button.hasAttribute('data-copy-bin')
        ? button.closest('[data-bin]')?.querySelector('[data-bin-body]')?.textContent.trim() || ''
        : button.hasAttribute('data-copy-student') ? studentDetails(form) : studentDetailValue(form, button.dataset.copyField);
    const status = button.querySelector('[data-copy-status]');
    try {
        if (!value) { status.textContent = 'Empty'; } else { await copyText(value); status.textContent = 'Copied ✓'; }
    } catch { status.textContent = 'Copy unavailable'; }
    setTimeout(() => { status.textContent = ''; }, 1800);
});
