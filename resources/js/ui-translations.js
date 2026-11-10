export function uiText(message) {
    if (typeof document === 'undefined') return message;
    const element = document.getElementById?.('ui-translations');
    if (!element) return message;
    const translations = element.uiTranslations ??= JSON.parse(element.textContent);
    return translations[message] ?? message;
}
