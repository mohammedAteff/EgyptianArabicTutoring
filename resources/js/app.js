import './analytics-telemetry.js';

import './form-builder.js';
import './clipboard.js';
import './announcement-banner.js';

import './staff-quick-actions.js';
import './admin-ux.js';
import './learning-player.js';
import './protected-video.js';
import './bunny-upload.js';
import './learning-progress.js';
import { initializeQuestionnaireSaves } from './questionnaire-save.js';

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initializeQuestionnaireSaves());
else initializeQuestionnaireSaves();
function initializeArticleEditors() {
    document.querySelectorAll('[data-blog-form]').forEach(form => {
        import('./blog-editor.js').then(({ initializeBlogEditor }) => initializeBlogEditor(form)).catch(() => {
            const status = form.querySelector('[data-save-status]');
            if (status) status.textContent = 'The visual editor could not load. Your content is available in HTML Source.';
        });
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeArticleEditors);
else initializeArticleEditors();
