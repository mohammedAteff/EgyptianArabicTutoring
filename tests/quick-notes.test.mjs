import test from 'node:test';
import assert from 'node:assert/strict';
import { QuickNoteDraft } from '../resources/js/quick-notes.js';

const note = (body, revision = 1, bookmarked = false) => ({ note: { id: 7, title: 'Stable title', body, revision, bookmarked } });
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { promise, resolve }; };

test('opening and whitespace do not create a note', async () => {
    let calls = 0;
    const draft = new QuickNoteDraft(async () => { calls++; }, () => {}, () => 'draft-a');
    await draft.flush(); draft.edit('  \n'); await draft.flush();
    assert.equal(calls, 0); assert.equal(draft.note, null);
});

test('rapid typing serializes creation then updates and never marks newer text saved early', async () => {
    const gate = deferred(); const calls = []; const states = [];
    const draft = new QuickNoteDraft(async (path, method, payload) => {
        calls.push({ path, method, ...payload });
        if (method === 'POST') { await gate.promise; return note(payload.body); }
        return note(payload.body, 2);
    }, d => states.push([d.state, d.body, d.savedBody]), () => 'draft-a');
    draft.edit('first'); const saving = draft.flush(); draft.edit('newer');
    assert.equal(calls.length, 1); gate.resolve(); await saving;
    assert.deepEqual(calls.map(c => [c.method, c.body]), [['POST', 'first'], ['PUT', 'newer']]);
    assert.equal(draft.body, 'newer'); assert.equal(draft.state, 'saved');
    assert.equal(states.some(([state, body, saved]) => state === 'saved' && body !== saved), false);
});

test('lost creation response retains key and original snapshot then queues newer text', async () => {
    const calls = []; let attempt = 0;
    const draft = new QuickNoteDraft(async (path, method, payload) => {
        calls.push({ path, method, ...payload });
        if (++attempt === 1) throw new Error('lost response');
        return note(payload.body, method === 'PUT' ? 2 : 1);
    }, () => {}, () => 'draft-a');
    draft.edit('committed'); assert.equal(await draft.flush(), false);
    draft.edit('later text'); await draft.flush(true);
    assert.deepEqual(calls.map(c => [c.method, c.body, c.creation_id]), [['POST', 'committed', 'draft-a'], ['POST', 'committed', 'draft-a'], ['PUT', 'later text', undefined]]);
    assert.equal(draft.body, 'later text'); assert.equal(draft.note.id, 7);
});

test('conflict preserves draft and blocks retries from overwriting current server content', async () => {
    let calls = 0;
    const draft = new QuickNoteDraft(async () => { calls++; return note('other editor', 3); }, () => {}, () => 'draft-a');
    draft.edit('local draft'); assert.equal(await draft.flush(), false);
    assert.equal(draft.state, 'conflict'); assert.equal(draft.body, 'local draft');
    await draft.flush(true); assert.equal(calls, 1);
});

test('clearing a saved note remains invalid and retains confirmed content', async () => {
    const draft = new QuickNoteDraft(async (_p, _m, payload) => note(payload.body), () => {}, () => 'draft-a');
    draft.edit('keep'); await draft.flush(); draft.edit('');
    assert.equal(await draft.flush(), false); assert.equal(draft.savedBody, 'keep'); assert.equal(draft.dirty, true);
});

test('bookmark ambiguity retries the same desired state and waits for saving', async () => {
    const calls = []; let fail = true;
    const draft = new QuickNoteDraft(async (path, method, payload) => {
        calls.push({ path, method, ...payload });
        if (path.endsWith('bookmark') && fail) { fail = false; throw new Error('lost response'); }
        return note(payload.body ?? 'write first', 1, payload.bookmarked ?? false);
    }, () => {}, () => 'draft-a');
    draft.edit('write first'); draft.bookmarkDesired = true;
    assert.equal(await draft.bookmark(), false); assert.equal(draft.bookmarkDesired, true);
    await draft.bookmark();
    assert.deepEqual(calls.map(c => c.method), ['POST', 'PUT', 'PUT']);
    assert.deepEqual(calls.slice(1).map(c => c.bookmarked), [true, true]);
    assert.equal(draft.note.bookmarked, true); assert.equal(draft.dirty, false);
});

test('rapid bookmark changes reconcile to the latest intended state', async () => {
    const gate = deferred(); let bookmarks = 0; const requested = [];
    const draft = new QuickNoteDraft(async (path, method, payload) => {
        if (!path.endsWith('bookmark')) return note(payload.body);
        requested.push(payload.bookmarked); if (++bookmarks === 1) await gate.promise;
        return note('body', 1, payload.bookmarked);
    }, () => {}, () => 'draft-a');
    draft.edit('body'); await draft.flush(); draft.bookmarkDesired = true;
    const saving = draft.bookmark(); await Promise.resolve(); await Promise.resolve();
    draft.bookmarkDesired = false; gate.resolve(); await saving;
    assert.deepEqual(requested, [true, false]); assert.equal(draft.note.bookmarked, false);
});

test('a late body-save response cannot undo a newer confirmed bookmark', async () => {
    const update = deferred(); const marking = deferred();
    const draft = new QuickNoteDraft(async (path, method, payload) => {
        if (method === 'POST') return note(payload.body);
        if (path.endsWith('bookmark')) { await marking.promise; return note('new body', 2, true); }
        await update.promise; return note(payload.body, 2, false);
    }, () => {}, () => 'draft-a');
    draft.edit('body'); await draft.flush();
    draft.bookmarkDesired = true;
    const bookmarkSaving = draft.bookmark(); await Promise.resolve();
    draft.edit('new body'); const saving = draft.flush();
    marking.resolve(); await bookmarkSaving;
    update.resolve(); await saving;
    assert.equal(draft.note.bookmarked, true);
});
