import test from 'node:test';
import assert from 'node:assert/strict';
import { createQuestionnaireSaveController } from './questionnaire-save.js';

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
