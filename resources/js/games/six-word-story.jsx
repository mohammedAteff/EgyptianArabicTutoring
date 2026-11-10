import React, {Component, useEffect, useRef, useState} from 'react';
import {createRoot} from 'react-dom/client';
import {createGameTelemetry} from '../game-telemetry.js';
import {createCopyFeedback} from '../clipboard.js';
import {LANGUAGES, DIFFICULTIES, generateRound, rememberRound, restoreHistory, revealDuration, numberedWords} from './six-word-story/engine.js';
import {createPromptClock} from './six-word-story/clock.js';
import {gameCopy, remainingLabel} from './six-word-story/copy.js';
import '../../css/six-word-story.css';

const preferenceKey = 'awa.games.six-word-story.preferences.v1';
const historyKey = 'awa.games.six-word-story.session.v1';
function sessionHistory() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(historyKey));
        return Object.fromEntries(LANGUAGES.map(({code}) => [code, restoreHistory(saved?.[code])]));
    } catch { return {}; }
}
function preferences() {
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem(preferenceKey)) || {}; } catch { /* Preferences are optional. */ }
    return {language: LANGUAGES.some(item => item.code === saved.language) ? saved.language : 'ar-eg', mode: saved.mode === 'wild' ? 'wild' : 'connected', difficulty: DIFFICULTIES.includes(saved.difficulty) ? saved.difficulty : 'easy', seconds: revealDuration(saved.seconds ?? 5), sound: typeof saved.sound === 'boolean' ? saved.sound : true};
}

function StorySymbol({mode}) {
    const points = mode === 'connected' ? [[7, 29], [17, 12], [30, 23], [42, 9], [55, 20], [66, 8]] : [[9, 10], [34, 8], [60, 14], [17, 31], [43, 28], [67, 34]];
    return <svg className="story-mode-symbol" viewBox="0 0 76 44" fill="none" aria-hidden="true">
        {mode === 'connected' && <path d="M7 29 17 12 30 23 42 9 55 20 66 8" stroke="currentColor" strokeWidth="1.5" />}
        {points.map(([x, y], position) => mode === 'connected' ? <circle key={position} cx={x} cy={y} r="4" fill={position % 2 ? 'var(--studio-gold)' : 'var(--studio-teal)'} stroke="var(--studio-surface)" strokeWidth="1.5" /> : <rect key={position} x={x - 5} y={y - 5} width="11" height="11" rx="2" transform={`rotate(${position % 2 ? 14 : -14} ${x} ${y})`} fill={position % 3 === 0 ? 'var(--studio-clay-soft)' : 'var(--studio-teal-soft)'} stroke="currentColor" />)}
    </svg>;
}

function StoryActionIcon({name}) {
    const paths = {
        repeat: 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8 M3 3v5h5',
        settings: 'm9.5 3-.5 2a7 7 0 0 0-1.4.8l-2-.6-2.5 4.3 1.5 1.4a7 7 0 0 0 0 1.6L3.1 14l2.5 4.3 2-.6A7 7 0 0 0 9 18.5l.5 2h5l.5-2a7 7 0 0 0 1.4-.8l2 .6 2.5-4.3-1.5-1.5a7 7 0 0 0 0-1.6l1.5-1.4-2.5-4.3-2 .6A7 7 0 0 0 15 5l-.5-2z',
        copy: 'M10.5 8h8a2.5 2.5 0 0 1 2.5 2.5v8a2.5 2.5 0 0 1-2.5 2.5h-8A2.5 2.5 0 0 1 8 18.5v-8A2.5 2.5 0 0 1 10.5 8Z M16 8V5.5A2.5 2.5 0 0 0 13.5 3h-8A2.5 2.5 0 0 0 3 5.5v8A2.5 2.5 0 0 0 5.5 16H8',
        check: 'm5 12 4 4L19 6',
    };
    return <svg className="story-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
        <path d={paths[name]} />
        {name === 'settings' && <circle cx="12" cy="11.75" r="3" />}
    </svg>;
}

function StoryGame({host}) {
    const locale = ['en', 'fr', 'de'].includes(host.dataset.uiLocale) ? host.dataset.uiLocale : 'en';
    const copy = gameCopy(locale);
    const [settings, setSettings] = useState(preferences);
    const [durationChoice, setDurationChoice] = useState(() => [3, 5, 10].includes(settings.seconds) ? String(settings.seconds) : 'custom');
    const [screen, setScreen] = useState('setup');
    const [words, setWords] = useState([]);
    const [index, setIndex] = useState(0);
    const [remaining, setRemaining] = useState(settings.seconds);
    const [attentive, setAttentive] = useState(!document.hidden && document.hasFocus());
    const [endedEarly, setEndedEarly] = useState(false);
    const [notice, setNotice] = useState('');
    const [copiedTarget, setCopiedTarget] = useState(null);
    const [clipboardFeedback] = useState(() => createCopyFeedback(setCopiedTarget));
    const [error, setError] = useState(false);
    const [destination, setDestination] = useState(null);
    const history = useRef(null), skips = useRef(0), clock = useRef(null), tracker = useRef(null), audio = useRef(null);
    if (history.current === null) history.current = sessionHistory();
    const heading = useRef(null), confirmButton = useRef(null), previousFocus = useRef(null), hasMounted = useRef(false), currentScreen = useRef(screen), currentIndex = useRef(index);
    currentScreen.current = screen; currentIndex.current = index;
    const language = LANGUAGES.find(item => item.code === settings.language);
    const remainingText = remainingLabel(locale, remaining);

    useEffect(() => { clipboardFeedback.reset(); }, [clipboardFeedback, screen, words]);
    useEffect(() => () => clipboardFeedback.destroy(), [clipboardFeedback]);

    useEffect(() => {
        try { localStorage.setItem(preferenceKey, JSON.stringify(settings)); } catch { /* Keep playing when storage is blocked. */ }
    }, [settings]);

    useEffect(() => {
        tracker.current = createGameTelemetry({url: host.dataset.trackUrl, csrf: document.querySelector('meta[name="csrf-token"]')?.content || '', enabled: host.dataset.trackingEnabled === 'true', heartbeat: true});
        return () => { tracker.current.interrupt({reason: 'page_left', revealed_count: currentIndex.current + 1}); tracker.current.destroy(); void audio.current?.close(); };
    }, [host]);

    useEffect(() => {
        const update = () => {
            const active = !document.hidden && document.hasFocus();
            clock.current?.tick(performance.now(), false);
            setAttentive(active);
        };
        const pagehide = event => {
            if (currentScreen.current !== 'round') return;
            if (event.persisted) tracker.current.activity();
            else tracker.current.interrupt({reason: 'page_left', revealed_count: currentIndex.current + 1});
        };
        const navigation = event => {
            const anchor = event.target.closest?.('a[href]');
            if (currentScreen.current !== 'round' || !anchor || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || anchor.target === '_blank' || anchor.hasAttribute('download') || anchor.getAttribute('href').startsWith('#')) return;
            event.preventDefault(); previousFocus.current = anchor; setDestination(anchor.href);
        };
        document.addEventListener('visibilitychange', update);
        window.addEventListener('blur', update); window.addEventListener('focus', update); window.addEventListener('pageshow', update); window.addEventListener('pagehide', pagehide);
        document.addEventListener('click', navigation);
        return () => { document.removeEventListener('visibilitychange', update); window.removeEventListener('blur', update); window.removeEventListener('focus', update); window.removeEventListener('pageshow', update); window.removeEventListener('pagehide', pagehide); document.removeEventListener('click', navigation); };
    }, []);

    useEffect(() => {
        host.dataset.playing = screen === 'round' ? 'true' : 'false';
        heading.current?.focus({preventScroll: true});
        if (hasMounted.current) host.scrollIntoView({block: 'start', behavior: 'instant'});
        hasMounted.current = true;
        return () => { delete host.dataset.playing; };
    }, [screen, host]);

    useEffect(() => {
        if (!destination) return;
        tracker.current.pause();
        confirmButton.current?.focus();
        const keyboard = event => {
            if (event.key === 'Escape') { setDestination(null); previousFocus.current?.focus(); }
            if (event.key === 'Tab') {
                const buttons = [...host.querySelectorAll('[role="dialog"] button')];
                const next = event.shiftKey ? (document.activeElement === buttons[0] ? buttons.at(-1) : null) : (document.activeElement === buttons.at(-1) ? buttons[0] : null);
                if (next) { event.preventDefault(); next.focus(); }
            }
        };
        document.addEventListener('keydown', keyboard);
        return () => { document.removeEventListener('keydown', keyboard); tracker.current.resume(); };
    }, [destination, host]);

    useEffect(() => {
        if (!notice) return;
        const timer = setTimeout(() => setNotice(''), 4000);
        return () => clearTimeout(timer);
    }, [notice]);

    const tone = () => {
        if (!settings.sound || !audio.current) return;
        try {
            void audio.current.resume();
            const oscillator = audio.current.createOscillator(), gain = audio.current.createGain(), time = audio.current.currentTime;
            oscillator.frequency.value = 440 + index * 35; gain.gain.setValueAtTime(.035, time); gain.gain.exponentialRampToValueAtTime(.0001, time + .12);
            oscillator.connect(gain); gain.connect(audio.current.destination); oscillator.start(time); oscillator.stop(time + .12);
        } catch { /* Visual play remains available without audio. */ }
    };

    const finish = (early = false) => {
        if (early) tracker.current.interrupt({reason: 'early_finish', revealed_count: index + 1});
        else tracker.current.complete({ending: skips.current ? 'advanced' : 'timed', skip_count: skips.current, revealed_count: 6, contract_version: 2});
        setEndedEarly(early); setScreen('recap');
    };

    const next = (skipped = false) => {
        if (skipped) skips.current++;
        if (index === 5) { finish(); return; }
        clock.current.next(settings.seconds, performance.now());
        setRemaining(settings.seconds); setIndex(value => value + 1);
    };

    useEffect(() => {
        if (screen !== 'round') return;
        tone();
    }, [screen, index]);

    useEffect(() => {
        if (screen !== 'round' || !attentive || destination) return;
        clock.current.tick(performance.now(), false);
        const timer = setInterval(() => {
            const milliseconds = clock.current.tick(performance.now());
            setRemaining(Math.ceil(milliseconds / 1000));
            if (milliseconds === 0) { clearInterval(timer); next(); }
        }, 100);
        return () => { clearInterval(timer); clock.current?.tick(performance.now(), false); };
    }, [screen, index, attentive, destination]);

    const start = () => {
        if (screen === 'round') tracker.current.interrupt({reason: 'restart', revealed_count: index + 1});
        try {
            const round = generateRound({...settings, history: history.current[settings.language]});
            history.current[settings.language] = rememberRound(history.current[settings.language], round, settings);
            try { sessionStorage.setItem(historyKey, JSON.stringify(history.current)); } catch { /* Keep session variety in memory when storage is blocked. */ }
            if (settings.sound && !audio.current) {
                try { const Audio = window.AudioContext || window.webkitAudioContext; if (Audio) audio.current = new Audio(); } catch { /* Audio is optional. */ }
            }
            tracker.current.start(settings.mode === 'connected' ? settings.difficulty : undefined, {ui_locale: locale, practice_language: settings.language, mode: settings.mode, reveal_seconds: settings.seconds, content_source: 'curated', contract_version: 2});
            clock.current = createPromptClock(settings.seconds, performance.now());
            skips.current = 0; setWords(round.words); setIndex(0); setRemaining(settings.seconds); setEndedEarly(false); setError(false); setScreen('round');
        } catch { setError(true); }
    };

    const copyWords = async (position = null) => {
        try {
            const copied = await clipboardFeedback.copy(position === null ? numberedWords(words) : words[position], position ?? 'all');
            if (copied) setNotice(position === null ? copy.copied : copy.wordCopied);
        }
        catch { setNotice(copy.copyFailed); }
    };
    const update = values => setSettings(current => ({...current, ...values}));

    return <div className="story-game" lang={locale} dir="ltr">
        <div className="story-topline"><a href={host.dataset.backUrl}>{copy.exit}</a><span>{copy.words}</span></div>
        {screen === 'setup' && <>
            <div className="story-intro"><div><p className="story-eyebrow">{copy.eyebrow}</p><h1 ref={heading} tabIndex={-1}>{copy.title}</h1><p className="story-intro-description">{copy.intro}</p><div className="story-intro-actions"><button className="story-primary" type="button" onClick={start}>{copy.start}<span aria-hidden="true">→</span></button><a href="#story-setup">{copy.settings}</a></div><p className="story-current-settings"><bdi lang={language.lang} dir={language.dir}>{language.label}</bdi><span aria-hidden="true">·</span>{copy[settings.mode]}</p></div><div className="story-cover"><img src={host.dataset.coverUrl} alt="" width="1536" height="864" /></div></div>
            <div className="story-setup" id="story-setup">
                <div className="story-choices">
                <fieldset><legend><span>01</span>{copy.practice}</legend><div className="story-languages">{LANGUAGES.map(item => <button key={item.code} className={settings.language === item.code ? 'is-selected' : ''} type="button" aria-pressed={settings.language === item.code} onClick={() => update({language: item.code})}><bdi lang={item.lang} dir={item.dir}>{item.label}</bdi></button>)}</div></fieldset>
                <fieldset><legend><span>02</span>{copy.mode}</legend><div className="story-modes">{['connected', 'wild'].map(mode => <button type="button" key={mode} className={settings.mode === mode ? 'is-selected' : ''} aria-pressed={settings.mode === mode} onClick={() => update({mode})}><StorySymbol mode={mode} /><strong>{copy[mode]}</strong><span>{copy[`${mode}Hint`]}</span></button>)}</div></fieldset>
                {settings.mode === 'connected' ? <fieldset><legend><span>03</span>{copy.difficulty}</legend><div className="story-difficulties">{DIFFICULTIES.map(value => <button key={value} type="button" aria-pressed={settings.difficulty === value} className={settings.difficulty === value ? 'is-selected' : ''} onClick={() => update({difficulty: value})}>{copy[value]}</button>)}</div></fieldset> : <p className="story-helper">{copy.wildDifficulty}</p>}
                </div>
                <aside className="story-settings" aria-label={copy.yourRound}>
                    <div className="story-settings-heading"><p className="story-eyebrow">{copy.yourRound}</p><bdi lang={language.lang} dir={language.dir}>{language.label}</bdi><p>{copy[settings.mode]}</p></div>
                    <fieldset><legend>{copy.timing}</legend><div className="story-durations">{['3', '5', '10', 'custom'].map(value => <button key={value} type="button" className={durationChoice === value ? 'is-selected' : ''} aria-pressed={durationChoice === value} aria-label={value === 'custom' ? copy.custom : `${value} ${copy.seconds}`} onClick={() => { setDurationChoice(value); if (value !== 'custom') update({seconds: Number(value)}); }}>{value === 'custom' ? copy.custom : value}</button>)}</div></fieldset>
                    {durationChoice === 'custom' && <label className="story-custom">{copy.timing}<input aria-label={copy.custom} type="number" min="1" max="60" step="1" inputMode="numeric" value={settings.seconds} onChange={event => update({seconds: revealDuration(event.target.value)})} /></label>}
                    <label className="story-sound"><span>{copy.sound}<small aria-hidden="true">{settings.sound ? copy.on : copy.off}</small></span><input type="checkbox" role="switch" checked={settings.sound} onChange={event => update({sound: event.target.checked})} aria-label={copy.sound} /><span className="story-switch" aria-hidden="true" /></label>
                    <div className="story-launch"><button className="story-primary" type="button" onClick={start}>{copy.start}<span aria-hidden="true">→</span></button><p>{copy.local}</p></div>
                </aside>
            </div>
        </>}
        {screen === 'round' && <section className="story-round" aria-label={copy.words}>
            <div className="story-round-meta"><div><bdi className="story-language-tag" lang={language.lang} dir={language.dir}>{language.label}</bdi><p>{copy[settings.mode]}</p></div><div className="story-timer" role="timer" aria-label={`${remaining} ${remainingText}`}><div className="story-timer-dial"><svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="28" /><circle className="story-timer-arc" cx="32" cy="32" r="28" pathLength="100" strokeDasharray="100" strokeDashoffset={100 * (1 - remaining / settings.seconds)} /></svg><strong>{remaining}</strong></div><span>{remainingText}</span></div></div>
            <div className="story-prompt-space"><p className="story-word-count">{copy.word} {index + 1} {copy.of} 6</p><h1 ref={heading} tabIndex={-1} className="story-prompt" data-long={words[index]?.length > 22} lang={language.lang} dir={language.dir} aria-live="polite" aria-atomic="true"><span key={index}>{words[index]}</span></h1><p>{copy.speak}</p></div>
            <div className="story-progress" role="progressbar" aria-label={copy.words} aria-valuemin="1" aria-valuemax="6" aria-valuenow={index + 1}>{words.map((_, position) => <span key={position} aria-hidden="true" className={position === index ? 'is-current' : position < index ? 'is-revealed' : ''}>{position + 1}</span>)}</div>
            {!attentive && <p className="story-helper" role="status">{copy.pause}</p>}
            <div className="story-round-actions"><button className="story-primary" type="button" onClick={() => next(true)}>{copy.skip} <span aria-hidden="true">→</span></button><button type="button" onClick={start}>{copy.restart}</button><button type="button" onClick={() => finish(true)}>{copy.finish}</button></div>
        </section>}
        {screen === 'recap' && <section className="story-recap">
            <p className="story-eyebrow">{copy.words}</p>
            <h1 ref={heading} tabIndex={-1}>{endedEarly ? copy.earlyEnd : copy.roundEnd}</h1>
            <p>{copy.recap}</p>
            <ol>{words.map((word, position) => <li key={position}>
                <div className="story-word-heading"><span className="story-recap-number" aria-hidden="true">0{position + 1}</span><button className="story-word-copy" type="button" onClick={() => copyWords(position)} data-copied={copiedTarget === position} aria-label={copiedTarget === position ? `${copy.wordCopied} ${word}` : copy.copyWordLabel.replace('{word}', word)} title={copiedTarget === position ? copy.wordCopied : copy.copyWord}><StoryActionIcon name={copiedTarget === position ? 'check' : 'copy'} /></button></div>
                <bdi lang={language.lang} dir={language.dir}>{word}</bdi>
            </li>)}</ol>
            <div className="story-recap-actions">
                <button className="story-primary" type="button" onClick={start}><StoryActionIcon name="repeat" />{copy.again}</button>
                <button type="button" onClick={() => setScreen('setup')}><StoryActionIcon name="settings" />{copy.settings}</button>
                <button type="button" onClick={() => copyWords()} data-copied={copiedTarget === 'all'} aria-label={copiedTarget === 'all' ? copy.copied : undefined}><StoryActionIcon name={copiedTarget === 'all' ? 'check' : 'copy'} />{copy.copy}</button>
            </div>
            <a className="story-lesson" href={host.dataset.lessonUrl} data-cta="booking">{copy.lesson} <span aria-hidden="true">→</span></a>
        </section>}
        {error && <div className="story-error" role="alert">{copy.error}<button type="button" onClick={start}>{copy.retry}</button></div>}
        <p className="story-notice" role="status" aria-live="polite">{notice}</p>
        {destination && <div className="story-dialog-backdrop"><section role="dialog" aria-modal="true" aria-labelledby="story-leave-title" aria-describedby="story-leave-description" className="story-dialog"><h2 id="story-leave-title">{copy.leaveTitle}</h2><p id="story-leave-description">{copy.leaveText}</p><div><button ref={confirmButton} className="story-primary" type="button" onClick={() => { setDestination(null); previousFocus.current?.focus(); }}>{copy.stay}</button><button type="button" onClick={() => { tracker.current.interrupt({reason: 'exit', revealed_count: index + 1}); window.location.assign(destination); }}>{copy.leave}</button></div></section></div>}
    </div>;
}

class GameBoundary extends Component {
    state = {failed: false};
    static getDerivedStateFromError() { return {failed: true}; }
    render() {
        const copy = gameCopy(this.props.host.dataset.uiLocale);
        return this.state.failed ? <div className="story-game story-error" role="alert"><p>{copy.error}</p><button type="button" onClick={() => this.setState({failed: false})}>{copy.retry}</button><a href={this.props.host.dataset.backUrl}>{copy.exit}</a></div> : <StoryGame host={this.props.host} />;
    }
}

const host = document.querySelector('[data-local-story-game]');
if (host?.dataset.playable === 'true') createRoot(host).render(<GameBoundary host={host} />);
