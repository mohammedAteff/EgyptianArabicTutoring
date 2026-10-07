import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {bookmarkSeconds, seekBookmark} from '../resources/js/learning-player.js';

test('bookmark captures elapsed video time to milliseconds without writing progress', () => {
    const media = {duration: 200, currentTime: 83.4567};
    assert.equal(bookmarkSeconds(media), 83.457);
    assert.deepEqual(media, {duration: 200, currentTime: 83.4567});
});

test('unknown, live, invalid and out of range times cannot be bookmarked', () => {
    for (const [duration, currentTime] of [[NaN, 0], [Infinity, 10], [0, 0], [50, -1], [50, 51], [50, NaN], [90000, 86401]]) {
        assert.equal(bookmarkSeconds({duration, currentTime}), null);
    }
    assert.equal(bookmarkSeconds({duration: 100, currentTime: 0}), 0);
});

test('bookmark seek uses its matching player and does not start playback', () => {
    const media = {duration: 200, currentTime: 0};
    assert.equal(seekBookmark(media, 83456), true);
    assert.equal(media.currentTime, 83.456);
    assert.equal(Object.hasOwn(media, 'play'), false);
});

test('a changed or unavailable video cannot seek beyond its duration', () => {
    for (const milliseconds of [-1, NaN, Infinity, 200001, 86400001]) {
        const media = {duration: 200, currentTime: 10};
        assert.equal(seekBookmark(media, milliseconds), false);
        assert.equal(media.currentTime, 10);
    }
    assert.equal(seekBookmark({duration: Infinity, currentTime: 0}, 12000), false);
});

function playerFixture() {
    const element = () => ({listeners: new Map(), addEventListener(event, callback) { this.listeners.set(event, callback); }});
    const videos = ['one', 'two'].map(id => ({...element(), dataset: {lmsVideo: id}, duration: NaN, currentTime: 0, readyState: 0, focused: false, scrolled: false, focus() {this.focused = true;}, scrollIntoView() {this.scrolled = true;}}));
    const forms = videos.map(video => {
        const button = element(), status = {textContent: ''}, seconds = {value: ''};
        return {...element(), button, status, seconds, submitted: 0, dataset: {lmsBookmarkForm: video.dataset.lmsVideo},
            elements: {namedItem: () => seconds}, querySelector: selector => selector === '[data-lms-save-bookmark]' ? button : status,
            requestSubmit() {this.submitted++;}};
    });
    const jump = {...element(), dataset: {lmsJumpBlock: 'two', lmsJumpPosition: '83000'}};
    const jumpStatus = {textContent: ''};
    const player = {querySelectorAll: selector => selector === '[data-lms-video]' ? videos : selector === '[data-lms-bookmark-form]' ? forms : [jump], querySelector: () => jumpStatus};
    const document = {readyState: 'complete', querySelectorAll: selector => selector === '[data-lms-player]' ? [player] : []};
    const source = fs.readFileSync(new URL('../resources/js/learning-player.js', import.meta.url), 'utf8').replace(/^export /gm, '');
    vm.runInNewContext(source, {document, window: {matchMedia: () => ({matches: false})}});
    return {videos, forms, jump, jumpStatus};
}

test('bookmark controls wait for metadata and report playback errors', () => {
    const {videos, forms} = playerFixture();
    assert.equal(forms[0].button.disabled, true);
    forms[0].button.listeners.get('click')();
    assert.equal(forms[0].submitted, 0);
    videos[0].duration = 120;
    videos[0].listeners.get('loadedmetadata')();
    assert.equal(forms[0].button.disabled, false);
    videos[0].listeners.get('error')();
    assert.equal(forms[0].button.disabled, true);
    assert.match(forms[0].status.textContent, /could not be loaded/);
});

test('each bookmark form submits the current time from its own video', () => {
    const {videos, forms} = playerFixture();
    videos[0].duration = videos[1].duration = 120;
    videos[0].currentTime = 15;
    videos[1].currentTime = 83.456;
    forms[1].button.listeners.get('click')();
    assert.equal(forms[1].submitted, 1);
    assert.equal(forms[1].seconds.value, '83.456');
    assert.equal(forms[0].submitted, 0);
    assert.equal(forms[0].seconds.value, '');
});

test('bookmark jumps wait for matching video metadata and never seek a different block', () => {
    const {videos, jump, jumpStatus} = playerFixture();
    jump.listeners.get('click')();
    assert.match(jumpStatus.textContent, /Waiting/);
    assert.equal(videos[1].currentTime, 0);
    videos[1].duration = 120;
    videos[1].listeners.get('loadedmetadata')();
    assert.equal(videos[1].currentTime, 83);
    assert.equal(videos[1].focused, true);
    assert.equal(videos[0].currentTime, 0);
    assert.equal(videos[0].focused, false);
});
