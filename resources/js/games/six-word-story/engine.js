import { ROUND_BANK, WILD_BANK } from './data/wordBanks.js';
import { EXTRA_ROUND_BANK, EXTRA_WILD_BANK } from './data/extraWordBanks.js';
import { MEGA_ROUND_BANK, MEGA_WILD_BANK } from './data/megaWordBanks.js';
import { ULTRA_ROUND_BANK, ULTRA_WILD_BANK } from './data/ultraWordBanks.js';
import { FRENCH_ROUND_BANK, FRENCH_WILD_BANK } from './data/frenchWordBanks.js';
import { GERMAN_ROUND_BANK, GERMAN_WILD_BANK } from './data/germanWordBanks.js';
import { SPANISH_ROUND_BANK, SPANISH_WILD_BANK } from './data/spanishWordBanks.js';
import { ITALIAN_ROUND_BANK, ITALIAN_WILD_BANK } from './data/italianWordBanks.js';
import { THEMES, PORTUGUESE_WILD } from './expansion.js';
import { SCENE_GROUPS, SCENE_LANGUAGE_ORDER } from './data/sceneVocabulary.js';
import { EGYPTIAN_SCENE_GROUPS } from './data/egyptianEverydayVocabulary.js';

export const LANGUAGES = [
    {code: 'ar-eg', label: 'مصري', name: 'Egyptian Arabic', lang: 'ar-EG', dir: 'rtl'},
    {code: 'ar', label: 'العربية', name: 'Arabic', lang: 'ar', dir: 'rtl'},
    {code: 'en', label: 'English', name: 'English', lang: 'en', dir: 'ltr'},
    {code: 'fr', label: 'Français', name: 'French', lang: 'fr', dir: 'ltr'},
    {code: 'de', label: 'Deutsch', name: 'German', lang: 'de', dir: 'ltr'},
    {code: 'es', label: 'Español', name: 'Spanish', lang: 'es', dir: 'ltr'},
    {code: 'it', label: 'Italiano', name: 'Italian', lang: 'it', dir: 'ltr'},
    {code: 'pt', label: 'Português', name: 'Portuguese', lang: 'pt', dir: 'ltr'},
];
export const DIFFICULTIES = ['easy', 'medium', 'hard', 'extreme'];
const connected = {}, wild = {};
const extraLanguages = {fr: [FRENCH_ROUND_BANK, FRENCH_WILD_BANK], de: [GERMAN_ROUND_BANK, GERMAN_WILD_BANK], es: [SPANISH_ROUND_BANK, SPANISH_WILD_BANK], it: [ITALIAN_ROUND_BANK, ITALIAN_WILD_BANK]};
const sceneGroups = SCENE_GROUPS.map(group => group.scenes.map(scene => scene.entries.split('\n').map(row => row.split('|'))));

export function normalizeEntry(value) {
    return value.normalize('NFKC').toLocaleLowerCase().replace(/\p{M}/gu, '').replace(/[^\p{L}\p{N}\s]/gu, ' ').replace(/\s+/g, ' ').trim();
}

for (const {code} of LANGUAGES) {
    connected[code] = Object.fromEntries(DIFFICULTIES.map(difficulty => [difficulty, [...(ROUND_BANK[code]?.[difficulty] || []), ...(EXTRA_ROUND_BANK[code]?.[difficulty] || []), ...(MEGA_ROUND_BANK[code]?.[difficulty] || []), ...(ULTRA_ROUND_BANK[code]?.[difficulty] || []), ...(extraLanguages[code]?.[0][difficulty] || [])]]));
    THEMES[code].forEach((theme, index) => {
        const entries = theme.split('|');
        const difficulty = DIFFICULTIES[Math.floor(index / 6)];
        for (let offset = 0; offset <= entries.length - 6; offset++) connected[code][difficulty].push(entries.slice(offset, offset + 6));
    });
    const column = SCENE_LANGUAGE_ORDER.indexOf(code) + 1;
    const languageSceneGroups = sceneGroups.map(group => group.map(scene => scene.map(row => [row[0], row[column]])));
    if (code === 'ar-eg') {
        languageSceneGroups.push(...EGYPTIAN_SCENE_GROUPS.map(group => group.scenes.map(scene => scene.entries.split('\n').map(row => row.split('|')))));
    }
    const sceneWords = {};
    for (const group of languageSceneGroups) {
        for (const scene of group) {
            for (const row of scene) (sceneWords[row[0]] ??= []).push(row[1]);
        }
        // Fixed scene paths: one scene for Easy, then two, three and six related scenes.
        for (const [difficulty, scenesPerRound, wordsPerScene] of [['easy', 1, 6], ['medium', 2, 3], ['hard', 3, 2], ['extreme', 6, 1]]) {
            for (let scene = 0; scene < group.length; scene += scenesPerRound) {
                for (let offset = 0; offset < group[scene].length; offset += wordsPerScene) {
                    connected[code][difficulty].push(group.slice(scene, scene + scenesPerRound).flatMap(entries => entries.slice(offset, offset + wordsPerScene).map(row => row[1])));
                }
            }
        }
    }
    for (const difficulty of DIFFICULTIES) {
        const seen = new Set();
        connected[code][difficulty] = connected[code][difficulty].filter(round => {
            const key = round.map(normalizeEntry).join('|');
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }
    const base = WILD_BANK[code] || extraLanguages[code]?.[1] || Object.fromEntries(Object.entries(PORTUGUESE_WILD).map(([shelf, entries]) => [shelf, entries.split('|')]));
    wild[code] = Object.fromEntries(Object.keys(base).map(shelf => {
        const entries = [...base[shelf], ...(EXTRA_WILD_BANK[code]?.[shelf] || []), ...(MEGA_WILD_BANK[code]?.[shelf] || []), ...(ULTRA_WILD_BANK[code]?.[shelf] || []), ...(sceneWords[shelf] || [])];
        const unique = new Map(entries.map(entry => [normalizeEntry(entry), entry]));
        return [shelf, [...unique.values()]];
    }));
}

export const BANKS = {connected, wild};

function shuffled(values, random) {
    const result = [...values];
    for (let index = result.length - 1; index > 0; index--) {
        const swap = Math.floor(random() * (index + 1));
        [result[index], result[swap]] = [result[swap], result[index]];
    }
    return result;
}

function compareRanks(first, second) {
    for (let index = 0; index < first.length; index++) {
        if (first[index] !== second[index]) return first[index] - second[index];
    }
    return 0;
}

function leastRanked(choices, rank) {
    let best = null, bestRank = null;
    for (const choice of choices) {
        const score = rank(choice);
        if (bestRank === null || compareRanks(score, bestRank) < 0) { best = choice; bestRank = score; }
    }
    return best;
}

export function restoreHistory(value) {
    const rounds = Number.isSafeInteger(value?.rounds) && value.rounds >= 0 ? value.rounds : 0;
    const validStats = stats => stats && Number.isSafeInteger(stats.count) && stats.count > 0 && stats.count <= rounds && Number.isSafeInteger(stats.last) && stats.last > 0 && stats.last <= rounds;
    const usage = Array.isArray(value?.usage) ? value.usage.slice(0, 20000).filter(item => Array.isArray(item) && typeof item[0] === 'string' && item[0].length <= 200 && validStats(item[1])) : [];
    const connected = Object.fromEntries(DIFFICULTIES.map(difficulty => [difficulty, Array.isArray(value?.connected?.[difficulty]) ? value.connected[difficulty].slice(0, 500).filter(item => Array.isArray(item) && Number.isInteger(item[0]) && item[0] >= 0 && validStats(item[1])) : []]));
    const recent = Array.isArray(value?.recent) ? value.recent.filter(entry => typeof entry === 'string' && entry.length <= 200).slice(-36).map(normalizeEntry) : [];
    return {rounds, usage, connected, recent};
}

export function rememberRound(history, round, {mode = 'connected', difficulty = 'easy'} = {}) {
    const current = history || restoreHistory(null);
    const rounds = current.rounds + 1;
    const usage = new Map(current.usage);
    for (const word of round.words) {
        const key = normalizeEntry(word);
        usage.set(key, {count: (usage.get(key)?.count || 0) + 1, last: rounds});
    }
    const connected = {...current.connected};
    if (mode !== 'wild') {
        const level = DIFFICULTIES.includes(difficulty) ? difficulty : 'easy';
        const seen = new Map(connected[level]);
        seen.set(round.index, {count: (seen.get(round.index)?.count || 0) + 1, last: rounds});
        connected[level] = [...seen];
    }
    return {rounds, usage: [...usage], connected, recent: rememberWords(current.recent, round.words)};
}

export function generateRound({language = 'ar-eg', mode = 'connected', difficulty = 'easy', recent = null, previous = null, history = null}, random = Math.random) {
    const code = Object.hasOwn(connected, language) ? language : 'ar-eg';
    const recentWords = recent ?? history?.recent ?? [];
    const unavailable = new Set(recentWords.map(normalizeEntry));
    const lastRound = new Set(recentWords.slice(-6).map(normalizeEntry));
    const usage = new Map(history?.usage || []);
    if (mode === 'wild') {
        const selected = new Set();
        const shelves = shuffled(Object.values(wild[code]), random).map(shelf => shuffled(shelf, random));
        const words = [];
        const wordRank = entry => {
            const key = normalizeEntry(entry), stats = usage.get(key);
            return [stats ? 1 : 0, unavailable.has(key) ? 1 : 0, stats?.count || 0, stats?.last || 0];
        };
        while (words.length < 6) {
            const candidates = shelves.map((shelf, index) => ({index, word: leastRanked(shelf.filter(entry => !selected.has(normalizeEntry(entry))), wordRank)})).filter(choice => choice.word !== null);
            const next = leastRanked(candidates, choice => wordRank(choice.word));
            words.push(next.word);
            selected.add(normalizeEntry(next.word));
            shelves.splice(next.index, 1);
        }
        return {words, index: null};
    }
    const level = DIFFICULTIES.includes(difficulty) ? difficulty : 'easy';
    const played = new Map(history?.connected?.[level] || []);
    const lastIndex = previous ?? [...played].sort((first, second) => second[1].last - first[1].last)[0]?.[0];
    const choices = shuffled(connected[code][level].map((words, index) => ({words, index})), random).filter(choice => choice.index !== lastIndex);
    const next = leastRanked(choices, choice => {
        const keys = choice.words.map(normalizeEntry);
        const overlap = keys.filter(key => lastRound.has(key)).length;
        return [overlap > 1 ? 1 : 0, played.get(choice.index)?.count || 0, keys.filter(key => unavailable.has(key)).length, keys.filter(key => usage.has(key)).length, keys.reduce((total, key) => total + (usage.get(key)?.count || 0), 0), played.get(choice.index)?.last || 0];
    });
    return {words: [...next.words], index: next.index};
}

export function rememberWords(recent, words) {
    return [...recent, ...words.map(normalizeEntry)].slice(-36);
}

export function numberedWords(words) {
    return words.map((word, position) => `${position + 1}. ${word}`).join('\n');
}

export function revealDuration(value) {
    const number = Number(value);
    return Number.isFinite(number) ? Math.min(60, Math.max(1, Math.round(number))) : 5;
}
