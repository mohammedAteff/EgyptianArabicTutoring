import test from 'node:test';
import assert from 'node:assert/strict';
import {BANKS, LANGUAGES, DIFFICULTIES, generateRound, rememberWords, rememberRound, restoreHistory, normalizeEntry, revealDuration} from '../resources/js/games/six-word-story/engine.js';
import {createPromptClock} from '../resources/js/games/six-word-story/clock.js';
import {gameCopy, remainingLabel} from '../resources/js/games/six-word-story/copy.js';
import {SCENE_GROUPS, SCENE_LANGUAGE_ORDER} from '../resources/js/games/six-word-story/data/sceneVocabulary.js';
import {EGYPTIAN_SCENE_GROUPS} from '../resources/js/games/six-word-story/data/egyptianEverydayVocabulary.js';

test('all eight ordered languages have usable expanded connected and category banks', () => {
    assert.deepEqual(LANGUAGES.map(item => item.code), ['ar-eg', 'ar', 'en', 'fr', 'de', 'es', 'it', 'pt']);
    for (const {code} of LANGUAGES) {
        for (const difficulty of DIFFICULTIES) {
            assert.ok(BANKS.connected[code][difficulty].length >= 260, `${code}/${difficulty}`);
            assert.ok(new Set(BANKS.connected[code][difficulty].flat().map(normalizeEntry)).size >= 1440, `${code}/${difficulty}: insufficient distinct vocabulary`);
            for (const words of BANKS.connected[code][difficulty]) {
                assert.equal(words.length, 6);
                assert.equal(new Set(words.map(normalizeEntry)).size, 6, `${code}: ${words.join(', ')}`);
                assert.ok(words.every(word => word.trim().length > 0));
            }
        }
        assert.equal(Object.keys(BANKS.wild[code]).length, 12);
        assert.ok(Object.values(BANKS.wild[code]).every(shelf => shelf.length >= 20));
        assert.ok(new Set(Object.values(BANKS.wild[code]).flat().map(normalizeEntry)).size >= 1600, `${code}: insufficient Wild Mix vocabulary`);
    }
});

test('scene paths have complete translations and known categories for every language', () => {
    assert.deepEqual([...SCENE_LANGUAGE_ORDER].sort(), LANGUAGES.map(item => item.code).sort());
    const categories = new Set(Object.keys(BANKS.wild.en));
    for (const group of SCENE_GROUPS) {
        assert.equal(group.scenes.length, 6, group.name);
        for (const scene of group.scenes) {
            const rows = scene.entries.split('\n').map(row => row.split('|'));
            assert.equal(rows.length, 18, `${group.name}/${scene.name}`);
            for (const row of rows) {
                assert.equal(row.length, 9, `${scene.name}: ${row[1]}`);
                assert.ok(categories.has(row[0]), `${scene.name}: ${row[0]}`);
                assert.ok(row.every(cell => cell.trim().length > 0), `${scene.name}: empty translation`);
            }
        }
    }
});

test('word banks use consistent natural case while preserving capitals and hyphens required by each language', () => {
    const englishCapitals = new Set(['Petri dish', 'USB stick', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', "New Year's Eve"]);
    for (const {code} of LANGUAGES) {
        const words = [...Object.values(BANKS.wild[code]).flat(), ...Object.values(BANKS.connected[code]).flat(2)];
        for (const word of words) {
            assert.equal(word, word.trim(), `${code}: stray spacing`);
            assert.ok(!/\s{2}|[_‐‑–—]/u.test(word), `${code}: inconsistent separators in ${word}`);
            assert.ok(word === word.toLocaleLowerCase(code) || word !== word.toLocaleUpperCase(code), `${code}: all-capital word ${word}`);
            if (code === 'en' && /^\p{Lu}/u.test(word)) assert.ok(englishCapitals.has(word), `en: unexpected capital in ${word}`);
            if (['fr', 'es', 'it', 'pt'].includes(code)) assert.ok(!/^\p{Lu}/u.test(word), `${code}: unnecessary initial capital in ${word}`);
        }
    }
    assert.ok(BANKS.wild.en.actions.includes('forget'));
    assert.ok(BANKS.wild.en.people.includes('cartographer'));
    assert.ok(BANKS.wild.en.people.includes('mother-in-law'));
    assert.ok(BANKS.wild.de.technology.includes('USB-Stick'));
    assert.ok(BANKS.wild.de.actions.includes('lesen'));
    assert.ok(BANKS.connected.de.easy.some(round => round.includes('Buch')));
});

test('Egyptian everyday scenes are playable in both modes at every level and remain in Egyptian practice', () => {
    const categories = new Set(Object.keys(BANKS.wild['ar-eg']));
    const additions = new Set();
    for (const group of EGYPTIAN_SCENE_GROUPS) {
        assert.equal(group.scenes.length, 6, group.name);
        for (const scene of group.scenes) {
            const rows = scene.entries.split('\n').map(row => row.split('|'));
            assert.equal(rows.length, 18, scene.name);
            for (const [category, word, extra] of rows) {
                assert.ok(categories.has(category), `${scene.name}: ${category}`);
                assert.equal(extra, undefined, scene.name);
                assert.ok(word?.trim(), `${scene.name}: empty word`);
                additions.add(normalizeEntry(word));
                assert.ok(BANKS.wild['ar-eg'][category].some(entry => normalizeEntry(entry) === normalizeEntry(word)), `${scene.name}: missing Wild Mix word`);
            }
        }
    }
    assert.ok(additions.has(normalizeEntry('الأجرة كام')));
    for (const difficulty of DIFFICULTIES) {
        const playable = new Set(BANKS.connected['ar-eg'][difficulty].flat().map(normalizeEntry));
        assert.ok([...additions].every(word => playable.has(word)), `${difficulty}: missing Egyptian scene words`);
    }
    for (const {code} of LANGUAGES.filter(language => language.code !== 'ar-eg')) {
        const otherWords = [...Object.values(BANKS.wild[code]).flat(), ...Object.values(BANKS.connected[code]).flat(2)].map(normalizeEntry);
        assert.ok(!otherWords.includes(normalizeEntry('الأجرة كام')), `${code}: Egyptian-only material leaked`);
    }
});

test('curated selection excludes recent entries and the previous connected round when possible', () => {
    const first = generateRound({language: 'ar-eg', difficulty: 'easy'}, () => .4);
    const recent = rememberWords([], first.words);
    const next = generateRound({language: 'ar-eg', difficulty: 'easy', previous: first.index, recent}, () => .4);
    assert.notEqual(next.index, first.index);
    assert.ok(next.words.every(word => !recent.includes(normalizeEntry(word))));
    assert.equal(rememberWords(Array(36).fill('old'), next.words).length, 36);
});

test('every language can generate six distinct wild prompts and invalid language safely falls back', () => {
    for (const {code} of LANGUAGES) {
        const round = generateRound({language: code, mode: 'wild'}, () => .3);
        assert.equal(round.words.length, 6);
        assert.equal(new Set(round.words.map(normalizeEntry)).size, 6);
    }
    assert.equal(generateRound({language: 'ar-franko'}, () => .3).words.length, 6);
});

function seededRandom(seed) {
    return () => {
        seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
        return seed / 4294967296;
    };
}

test('two hours of connected play avoid consecutive overlap above one word when a suitable round exists', () => {
    for (const {code} of LANGUAGES) {
        for (const difficulty of DIFFICULTIES) {
            let history = restoreHistory(null), previousIndex = null;
            const random = seededRandom(71);
            for (let number = 0; number < 240; number++) {
                const previousWords = new Set(history.recent.slice(-6));
                const round = generateRound({language: code, difficulty, history}, random);
                const overlap = round.words.filter(word => previousWords.has(normalizeEntry(word))).length;
                assert.notEqual(round.index, previousIndex, `${code}/${difficulty}: consecutive identical round`);
                assert.ok(overlap <= 1, `${code}/${difficulty}: ${overlap} consecutive repeated words in round ${number}`);
                history = rememberRound(history, round, {difficulty});
                previousIndex = round.index;
            }
            assert.equal(history.rounds, 240);
            assert.ok(history.usage.length >= 1300, `${code}/${difficulty}: too much recycling over 1440 word appearances (${history.usage.length} distinct)`);
        }
    }
});

test('wild play favours fresh vocabulary and retains six distinct words over two hours', () => {
    for (const {code} of LANGUAGES) {
        let history = restoreHistory(null);
        const random = seededRandom(103);
        for (let number = 0; number < 240; number++) {
            const previousWords = new Set(history.recent.slice(-6));
            const round = generateRound({language: code, mode: 'wild', history}, random);
            assert.equal(new Set(round.words.map(normalizeEntry)).size, 6);
            assert.equal(round.words.filter(word => previousWords.has(normalizeEntry(word))).length, 0);
            history = rememberRound(history, round, {mode: 'wild'});
        }
        assert.ok(history.usage.length >= 1300, `${code}: too much recycling over 1440 word appearances (${history.usage.length} distinct)`);
        const counts = history.usage.map(([, stats]) => stats.count);
        assert.ok(Math.max(...counts) - Math.min(...counts) <= 2, `${code}: uneven recycling`);
    }
});

test('session history survives refresh and shares word usage when switching modes', () => {
    let history = restoreHistory(null);
    const first = generateRound({language: 'ar-eg', difficulty: 'easy', history}, seededRandom(3));
    history = rememberRound(history, first, {difficulty: 'easy'});
    const restored = restoreHistory(JSON.parse(JSON.stringify(history)));
    assert.deepEqual(restored, history);
    const next = generateRound({language: 'ar-eg', difficulty: 'easy', history: restored}, seededRandom(3));
    assert.notEqual(first.index, next.index);
    assert.ok(next.words.every(word => !new Set(first.words.map(normalizeEntry)).has(normalizeEntry(word))));
    const wild = generateRound({language: 'ar-eg', mode: 'wild', history: restored}, seededRandom(3));
    assert.ok(wild.words.every(word => !new Set(first.words.map(normalizeEntry)).has(normalizeEntry(word))));
    assert.deepEqual(restoreHistory({rounds: 'broken', recent: [null, 5], usage: [['word', null]], connected: {easy: 'bad'}}), restoreHistory(null));
});

test('prompt clock pauses background time and restarts each new prompt independently', () => {
    const clock = createPromptClock(5, 0);
    assert.equal(clock.tick(2000), 3000);
    assert.equal(clock.tick(62000, false), 3000);
    assert.equal(clock.tick(64000), 1000);
    assert.equal(clock.tick(65000), 0);
    clock.next(3, 65000);
    assert.equal(clock.tick(66000), 2000);
    assert.equal(clock.tick(90000, false), 2000);
});

test('game interface translations cover every message and use locale-specific countdown grammar', () => {
    const expected = {
        en: {zero: 'seconds left', one: 'second left', two: 'seconds left', label: 'Copy word: مَرْحَبًا'},
        fr: {zero: 'seconde restante', one: 'seconde restante', two: 'secondes restantes', label: 'Copier le mot : مَرْحَبًا'},
        de: {zero: 'Sekunden übrig', one: 'Sekunde übrig', two: 'Sekunden übrig', label: 'Wort kopieren: مَرْحَبًا'},
    };
    for (const [locale, labels] of Object.entries(expected)) {
        const copy = gameCopy(locale);
        for (const [key, value] of Object.entries(copy)) {
            assert.equal(typeof value, 'string', `${locale}/${key}`);
            assert.ok(value.trim(), `${locale}/${key}: empty message`);
            if (locale !== 'en') assert.notEqual(value, gameCopy('en')[key], `${locale}/${key}: English fallback`);
        }
        assert.equal(remainingLabel(locale, 0), labels.zero);
        assert.equal(remainingLabel(locale, 1), labels.one);
        assert.equal(remainingLabel(locale, 2), labels.two);
        assert.equal(copy.copyWordLabel.replace('{word}', 'مَرْحَبًا'), labels.label);
        assert.ok(!copy.copyFailed.includes('below') && !copy.copyFailed.includes('ci-dessous') && !copy.copyFailed.includes('unten'));
    }
    for (const unsupported of ['unsupported', 'constructor', '__proto__', undefined]) {
        assert.equal(gameCopy(unsupported), gameCopy('en'));
        assert.equal(remainingLabel(unsupported, 1), 'second left');
    }
});

test('custom duration is finite and bounded, and UI copy is independent of practice choices', () => {
    assert.equal(revealDuration(''), 1);
    assert.equal(revealDuration(0), 1);
    assert.equal(revealDuration(100), 60);
    assert.equal(revealDuration('invalid'), 5);
    assert.equal(revealDuration(4.8), 5);
    assert.equal(gameCopy('unsupported').start, gameCopy('en').start);
    for (const locale of ['fr', 'de']) assert.deepEqual(Object.keys(gameCopy(locale)).sort(), Object.keys(gameCopy('en')).sort());
});
