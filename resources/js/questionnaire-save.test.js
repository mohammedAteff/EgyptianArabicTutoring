import test from 'node:test';
import assert from 'node:assert/strict';
import { createQuestionnaireSaveController, initializeQuestionnaireSaves } from './questionnaire-save.js';

function fixture(persist = async () => {}) {
    const statuses = [];
    const timers = new Map();
    let nextId = 0;
    let answers = { goal: 'First answer' };
    const controller = createQuestionnaireSaveController({
        capture: () => ({ ...answers }),
        persist,
        report: (state, error) => statuses.push([state, error?.message]),
        schedule: callback => { timers.set(++nextId, callback); return nextId; },
        cancel: id => timers.delete(id),
    });
    return { controller, statuses, timers, edit: value => { answers.goal = value; controller.changed(); } };
}

test('the final edit is pending and protected before the debounce runs', () => {
    const { controller, statuses, timers, edit } = fixture();
    assert.equal(controller.shouldWarn(), false);
    edit('Unsaved final answer');
    assert.equal(statuses.at(-1)[0], 'pending');
    assert.equal(controller.shouldWarn(), true);
    assert.equal(timers.size, 1);
});

test('a successful acknowledged save clears the navigation warning', async () => {
    const received = [];
    const { controller, statuses, edit } = fixture(async answers => received.push(answers));
    edit('Saved answer');
    await controller.save();
    assert.deepEqual(received, [{ goal: 'Saved answer' }]);
    assert.equal(statuses.at(-1)[0], 'saved');
    assert.equal(controller.shouldWarn(), false);
});

test('an older acknowledgement cannot mark a newer edit as saved', async () => {
    let resolve;
    const received = [];
    const { controller, statuses, edit } = fixture(answers => {
        received.push(answers);
        return new Promise(done => { resolve = done; });
    });
    edit('First snapshot');
    const first = controller.save();
    await Promise.resolve();
    edit('Latest snapshot');
    resolve();
    await first;
    assert.equal(statuses.at(-1)[0], 'pending');
    assert.equal(controller.shouldWarn(), true);
    const second = controller.save();
    await Promise.resolve();
    resolve();
    await second;
    assert.deepEqual(received, [{ goal: 'First snapshot' }, { goal: 'Latest snapshot' }]);
    assert.equal(controller.shouldWarn(), false);
});

test('save failures retain pending answers and do not retry endlessly', async () => {
    const { controller, statuses, timers, edit } = fixture(async () => { throw new Error('Network unavailable'); });
    edit('Keep this answer');
    await controller.save();
    assert.deepEqual(statuses.at(-1), ['error', 'Network unavailable']);
    assert.equal(controller.shouldWarn(), true);
    assert.equal(timers.size, 0);
});

test('a retry can save the retained latest answer after a failure', async () => {
    let attempts = 0;
    const received = [];
    const { controller, edit } = fixture(async answers => {
        if (++attempts === 1) throw new Error('Temporary failure');
        received.push(answers);
    });
    edit('Retained answer');
    await controller.save();
    await controller.save();
    assert.deepEqual(received, [{ goal: 'Retained answer' }]);
    assert.equal(controller.shouldWarn(), false);
});

test('final submission waits for in-flight autosave before allowing native navigation', async () => {
    let resolve;
    const { controller, edit, timers } = fixture(() => new Promise(done => { resolve = done; }));
    edit('Draft answer');
    const request = controller.save();
    await Promise.resolve();
    edit('Final answer included in native POST');
    let ready = false;
    const preparation = controller.prepareSubmission().then(() => { ready = true; });
    await Promise.resolve();
    assert.equal(ready, false);
    assert.equal(controller.shouldWarn(), true);
    assert.equal(timers.size, 0);
    resolve();
    await request;
    await preparation;
    assert.equal(ready, true);
    assert.equal(controller.shouldWarn(), true);
    controller.allowSubmissionNavigation();
    assert.equal(controller.shouldWarn(), false);
});

test('cancelled native validation keeps the last edit protected', async () => {
    const { controller, edit } = fixture();
    edit('Incomplete final answer');
    await controller.prepareSubmission();
    controller.cancelSubmission();
    assert.equal(controller.shouldWarn(), true);
});

test('new input after a submitted-page return restores autosave and protection', async () => {
    const { controller, edit } = fixture();
    edit('Submitted answer');
    await controller.prepareSubmission();
    controller.allowSubmissionNavigation();
    edit('A new edit');
    assert.equal(controller.shouldWarn(), true);
    await controller.save();
    assert.equal(controller.shouldWarn(), false);
});

test('a second save call shares the in-flight request', async () => {
    let resolve;
    let count = 0;
    const { controller, edit } = fixture(() => { count++; return new Promise(done => { resolve = done; }); });
    edit('One request');
    const first = controller.save();
    const second = controller.save();
    assert.equal(first, second);
    await Promise.resolve();
    assert.equal(count, 1);
    resolve();
    await first;
});

test('edits during submission preparation do not schedule a late autosave', async () => {
    const { controller, edit, timers } = fixture();
    edit('Initial answer');
    await controller.prepareSubmission();
    edit('Latest answer for native POST');
    assert.equal(timers.size, 0);
    assert.equal(controller.shouldWarn(), true);
    controller.allowSubmissionNavigation();
    edit('A genuine subsequent edit');
    assert.equal(timers.size, 1);
});

// This models the integration lifecycle; actual native browser input is verified separately.
function submissionFixture() {
    const browser = new EventTarget();
    const status = { dataset: {}, textContent: '' };
    const requests = [];
    const attempts = [];
    const form = new EventTarget();
    Object.assign(form, {
        dataset: {},
        questionnaireValues: () => ({}),
        querySelector: () => status,
        firing: false,
        valid: true,
        requestSubmit(submitter) {
            attempts.push(submitter);
            if (this.firing) return;
            if (submitter && submitter.form !== this) throw new Error('Submitter removed');
            if (!submitter?.formNoValidate && !this.valid) return;
            const event = new Event('submit', { cancelable: true });
            event.submitter = submitter;
            if (this.dispatchEvent(event)) requests.push(submitter?.value ?? null);
        },
    });
    const originalWindow = globalThis.window;
    globalThis.window = browser;
    try {
        initializeQuestionnaireSaves({ querySelectorAll: () => [form] });
    } finally {
        globalThis.window = originalWindow;
    }
    const draft = { form, value: 'draft', formNoValidate: true };
    const submit = { form, value: 'submit', formNoValidate: false };
    const activate = async (submitter = submit) => {
        form.firing = true;
        const event = new Event('submit', { cancelable: true });
        event.submitter = submitter;
        form.dispatchEvent(event);
        await Promise.resolve();
        form.firing = false;
    };
    const nextTask = () => new Promise(resolve => setTimeout(resolve, 0));
    return { form, browser, status, draft, submit, activate, nextTask, requests, attempts };
}

test('settled submission escapes the firing-events guard and preserves the chosen submitter', async () => {
    const { activate, nextTask, submit, requests, attempts } = submissionFixture();
    await activate(submit);
    await nextTask();
    assert.deepEqual(requests, ['submit']);
    assert.deepEqual(attempts, [submit]);
});

test('incomplete draft keeps its validation exemption and rapid activation makes one native request', async () => {
    const { form, activate, nextTask, draft, requests } = submissionFixture();
    form.valid = false;
    await activate(draft);
    await activate(draft);
    await nextTask();
    await activate(draft);
    await nextTask();
    assert.deepEqual(requests, ['draft']);
});

test('cancelled native validation permits a corrected retry', async () => {
    const { form, activate, nextTask, requests } = submissionFixture();
    form.valid = false;
    await activate();
    await nextTask();
    assert.deepEqual(requests, []);
    form.valid = true;
    await activate();
    await nextTask();
    assert.deepEqual(requests, ['submit']);
});

test('a removed submitter reports a usable retry without silently changing intent', async () => {
    const { form, activate, nextTask, draft, submit, status, requests } = submissionFixture();
    form.dispatchEvent(new Event('input'));
    await activate(draft);
    draft.form = null;
    await nextTask();
    assert.deepEqual(requests, []);
    assert.equal(status.dataset.saveState, 'error');
    await activate(submit);
    await nextTask();
    assert.deepEqual(requests, ['submit']);
});

test('another listener cancelling the reissued event permits a later retry', async () => {
    const { form, activate, nextTask, requests, status } = submissionFixture();
    const cancel = event => event.preventDefault();
    form.addEventListener('submit', cancel);
    await activate();
    await nextTask();
    assert.deepEqual(requests, []);
    assert.equal(status.dataset.saveState, 'idle');
    form.removeEventListener('submit', cancel);
    await activate();
    await nextTask();
    assert.deepEqual(requests, ['submit']);
});

test('cancelling a clean acknowledged submission restores the saved status', async () => {
    const { controller, edit, statuses } = fixture();
    edit('Acknowledged answer');
    await controller.save();
    await controller.prepareSubmission();
    controller.allowSubmissionNavigation();
    controller.cancelSubmission();
    assert.equal(statuses.at(-1)[0], 'saved');
    assert.equal(controller.shouldWarn(), false);
});

test('a persisted history return clears the submission lock', async () => {
    const { activate, nextTask, browser, requests } = submissionFixture();
    await activate();
    await nextTask();
    const returned = new Event('pageshow');
    returned.persisted = true;
    browser.dispatchEvent(returned);
    await activate();
    await nextTask();
    assert.deepEqual(requests, ['submit', 'submit']);
});
