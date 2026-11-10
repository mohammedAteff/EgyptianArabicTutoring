import test from 'node:test';
import assert from 'node:assert/strict';
import { automaticallyReadFeedback, feedbackRequestUrl } from '../resources/js/feedback.js';

function page(hidden = false) {
    const listeners = new Map();
    const frames = [];
    let submissions = 0;
    const documentRoot = { hidden, addEventListener: (name, callback) => listeners.set(name, callback) };
    const form = { isConnected: true, dataset: {}, requestSubmit: () => { submissions++; } };
    automaticallyReadFeedback(form, documentRoot, callback => frames.push(callback));
    return {
        form, documentRoot,
        submissions: () => submissions,
        render: () => { while (frames.length) frames.shift()(); },
        visibility: hidden => { documentRoot.hidden = hidden; listeners.get('visibilitychange')(); },
    };
}

test('a visible student conversation requests acknowledgment after rendering', () => {
    const tab = page();
    assert.equal(tab.submissions(), 0);
    tab.render();
    assert.equal(tab.submissions(), 1);
});

test('opening in a background tab defers acknowledgment until that tab is visible', () => {
    const tab = page(true);
    tab.render();
    assert.equal(tab.submissions(), 0);
    tab.visibility(false);
    tab.render();
    assert.equal(tab.submissions(), 1);
});

test('leaving or hiding the conversation before rendering does not acknowledge it', () => {
    const hidden = page();
    hidden.visibility(true);
    hidden.render();
    assert.equal(hidden.submissions(), 0);
    hidden.visibility(false);
    hidden.render();
    assert.equal(hidden.submissions(), 1);
    const left = page();
    left.form.isConnected = false;
    left.render();
    assert.equal(left.submissions(), 0);
});

test('a confirmed acknowledgment is not sent again when returning to the tab', () => {
    const tab = page();
    tab.render();
    tab.form.dataset.feedbackReadComplete = 'true';
    tab.visibility(true);
    tab.visibility(false);
    tab.render();
    assert.equal(tab.submissions(), 1);
});

test('feedback requests retain the open form language independently of another tab', () => {
    for (const locale of ['en', 'fr', 'de']) {
        const request = feedbackRequestUrl('http://arabicwabdallah.test/student/feedback/2?segment=3&lang=de', locale);
        assert.equal(request.searchParams.get('lang'), locale);
        assert.equal(request.searchParams.get('segment'), '3');
        assert.equal(request.origin, 'http://arabicwabdallah.test');
        assert.equal(request.pathname, '/student/feedback/2');
    }
});

test('localized context requests retain filters and reject unsupported language overrides', () => {
    const request = feedbackRequestUrl('http://arabicwabdallah.test/student/feedback/contexts?type=lesson&q=Arabic', 'fr');
    assert.equal(request.href, 'http://arabicwabdallah.test/student/feedback/contexts?type=lesson&q=Arabic&lang=fr');
    assert.equal(feedbackRequestUrl(request.href, 'unsupported').href, request.href);
});
