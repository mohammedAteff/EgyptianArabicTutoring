export function createQuestionnaireSaveController({ capture, persist, report, schedule = setTimeout, cancel = clearTimeout }) {
    let revision = 0;
    let acknowledged = 0;
    let timer = null;
    let inFlight = null;
    let preparing = false;
    let navigationAllowed = false;
    const clearTimer = () => {
        if (timer !== null) cancel(timer);
        timer = null;
    };
    const queue = (delay = 700) => {
        clearTimer();
        timer = schedule(() => { timer = null; void save(); }, delay);
    };
    const save = () => {
        clearTimer();
        if (inFlight) return inFlight;
        if (preparing || revision === acknowledged) return Promise.resolve();
        const sentRevision = revision;
        const snapshot = capture();
        report('saving');
        inFlight = Promise.resolve().then(() => persist(snapshot)).then(() => {
            acknowledged = sentRevision;
            report(revision === acknowledged ? 'saved' : 'pending');
        }).catch(error => {
            report('error', error);
        }).finally(() => {
            inFlight = null;
            if (!preparing && revision > sentRevision) queue(0);
        });
        return inFlight;
    };
    return {
        changed() {
            navigationAllowed = false;
            if (preparing && !inFlight) preparing = false;
            revision += 1;
            report('pending');
            if (!preparing) queue();
        },
        save,
        shouldWarn() { return !navigationAllowed && (revision !== acknowledged || inFlight !== null); },
        async prepareSubmission() {
            preparing = true;
            clearTimer();
            if (inFlight) await inFlight;
        },
        allowSubmissionNavigation() {
            navigationAllowed = true;
            report('submitting');
        },
        cancelSubmission() {
            navigationAllowed = false;
            preparing = false;
            if (revision !== acknowledged) report('pending');
        },
    };
}

export function initializeQuestionnaireSaves(root = document) {
    root.querySelectorAll('[data-questionnaire]').forEach(form => {
        if (form.dataset.readOnly === '1' || form.dataset.saveInitialized || !form.questionnaireValues) return;
        form.dataset.saveInitialized = '1';
        const status = form.querySelector('[data-save-status]');
        const controller = createQuestionnaireSaveController({
            capture: () => form.questionnaireValues(),
            persist: async answers => {
                let response;
                try {
                    response = await fetch(form.dataset.autosaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                        body: JSON.stringify({ answers, submission_id: form.querySelector('[name="submission_id"]').value || null, form_version_id: Number(form.querySelector('[name="form_version_id"]').value) }),
                    });
                } catch {
                    throw new Error("Your latest changes couldn't be saved. Check your connection and use Save Draft to try again. Keep this page open until your answers are saved.");
                }
                if (!response.ok) throw new Error(response.status === 409
                    ? "Your answers couldn't be saved. The questionnaire or response has changed. Keep this page open and review the update before leaving."
                    : "Your latest changes couldn't be saved. Keep this page open and use Save Draft to try again.");
                let data;
                try { data = await response.json(); } catch {
                    throw new Error("Your latest changes couldn't be saved. Keep this page open and try Save Draft.");
                }
                if (!Number.isSafeInteger(data?.draft_id) || data.draft_id < 1) {
                    throw new Error("Your latest changes couldn't be saved. Keep this page open and try Save Draft.");
                }
                form.querySelector('[name="submission_id"]').value = data.draft_id;
            },
            report: (state, error) => {
                status.dataset.saveState = state;
                status.textContent = {
                    pending: 'Unsaved changes — waiting to save.',
                    saving: 'Saving...',
                    saved: 'Saved',
                    submitting: 'Saving your answers...',
                    error: error?.message || "Your latest changes couldn't be saved. Keep this page open and try Save Draft.",
                }[state];
            },
        });
        if (form.dataset.earlyDirty === '1') controller.changed();
        form.addEventListener('input', () => controller.changed());
        form.addEventListener('change', () => controller.changed());
        window.addEventListener('beforeunload', event => {
            if (!controller.shouldWarn()) return;
            event.preventDefault();
            event.returnValue = '';
        });
        window.addEventListener('pageshow', event => {
            if (event.persisted) controller.cancelSubmission();
        });
        let preparing = false;
        let reissuing = false;
        let nativeSubmission = false;
        form.addEventListener('submit', async event => {
            if (reissuing) {
                nativeSubmission = true;
                controller.allowSubmissionNavigation();
                return;
            }
            event.preventDefault();
            if (preparing) return;
            preparing = true;
            nativeSubmission = false;
            const submitter = event.submitter;
            await controller.prepareSubmission();
            try {
                reissuing = true;
                if (submitter?.form === form) form.requestSubmit(submitter);
                else form.requestSubmit();
            } finally {
                reissuing = false;
                preparing = false;
                if (!nativeSubmission) controller.cancelSubmission();
            }
        });
    });
}
