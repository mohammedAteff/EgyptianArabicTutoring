export function editableFormState(form) {
    return JSON.stringify(Array.from(form.elements).filter(field => field.name && !['submit', 'button'].includes(field.type) && (field.type !== 'hidden' || field.dataset?.trackUnsaved === '1')).map(field => [field.name, field.type === 'checkbox' || field.type === 'radio' ? field.checked : field.type === 'select-multiple' ? Array.from(field.selectedOptions, option => option.value) : field.value]));
}

if (typeof document !== 'undefined') {
    const initialize = () => {
        if (!document.querySelector('[data-admin-ux]')) return;
        const states = new Map();
        const submitted = new Set();
        const capture = form => {
            if (form && (form.getAttribute('method') || 'get').toLowerCase() !== 'get' && !states.has(form)) states.set(form, editableFormState(form));
        };
        document.querySelectorAll('form').forEach(capture);
        document.addEventListener('admin-form-ready', event => {
            const form = event.target.closest('form');
            if (form && states.has(form)) states.set(form, editableFormState(form));
            else capture(form);
        });
        document.addEventListener('change', event => {
            const picker = event.target.closest('[data-staff-template-picker]');
            if (!picker || !picker.value) return;
            const field = picker.form?.elements.namedItem(picker.dataset.targetField);
            if (!field) return;
            field.value = picker.value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.focus();
            picker.value = '';
        });
        document.addEventListener('focusin', event => capture(event.target.form));
        document.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            submitted.add(event.target);
            setTimeout(() => { if (event.defaultPrevented) submitted.delete(event.target); }, 0);
        });
        window.addEventListener('beforeunload', event => {
            const dirty = Array.from(states).some(([form, initial]) => form.isConnected && !submitted.has(form) && editableFormState(form) !== initial);
            if (!dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}
