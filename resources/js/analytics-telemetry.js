/**
 * First-Party Granular Telemetry & Engagement Tracker
 * Egyptian Arabic with Abdallah - Analytics Telemetry Pipeline
 */

(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    // Generate cryptographic or pseudo-random UUID v4
    function generateUuid() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = (Math.random() * 16) | 0;
            const v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function detectTemplate() {
        const basePath = (document.querySelector('meta[name="analytics-base-path"]')?.content || '').replace(/\/$/, '');
        const pathname = window.location.pathname;
        const path = basePath && (pathname === basePath || pathname.startsWith(basePath + '/'))
            ? pathname.slice(basePath.length) || '/'
            : pathname;
        if (path === '/' || path === '/fr' || path === '/de') return 'landing';
        if (path.includes('/blog')) return 'blog';
        if (path.includes('/resources') || path.includes('/ressources') || path.includes('/ressourcen')) return 'resource';
        if (path.includes('/games') || path.includes('/jeux') || path.includes('/spiele')) return 'game';
        if (path.includes('/pricing') || path.includes('/tarifs') || path.includes('/preise')) return 'pricing';
        return 'general';
    }

    const pageTemplate = detectTemplate();
    const currentPath = window.location.pathname;

    let eventQueue = [];
    const sentEventUuids = new Set();
    const viewedSections = new Set();

    // Map section IDs to element and intersection ratio
    const observedSections = new Map();

    function enqueueEvent(eventName, metadata = {}) {
        const eventUuid = generateUuid();
        const payload = {
            event_uuid: eventUuid,
            event_name: eventName,
            page: window.location.href.substring(0, 500),
            metadata: Object.assign({}, metadata, {
                path: currentPath.substring(0, 200),
                page_template: pageTemplate,
            }),
        };
        eventQueue.push(payload);
        if (eventQueue.length >= 10) {
            flushQueue();
        }
    }

    function setupPageEvents() {
        document.querySelectorAll('[data-analytics-event]').forEach(function (element) {
            const eventName = element.dataset.analyticsEvent;
            if (!eventName) return;

            let metadata = {};
            try {
                metadata = JSON.parse(element.dataset.analyticsMetadata || '{}');
            } catch (e) {
                metadata = {};
            }

            enqueueEvent(eventName, metadata);
            element.removeAttribute('data-analytics-event');
            element.removeAttribute('data-analytics-metadata');
            flushQueue();
        });
    }

    function flushQueue(isExit = false) {
        if (eventQueue.length === 0) return;

        // Take up to 20 events per batch (server bounds limit)
        const batch = eventQueue.splice(0, 20);
        const unsentBatch = batch.filter(evt => !sentEventUuids.has(evt.event_uuid));

        if (unsentBatch.length === 0) return;

        unsentBatch.forEach(evt => sentEventUuids.add(evt.event_uuid));

        const csrfToken = getCsrfToken();
        const payloadString = JSON.stringify({ events: unsentBatch, _token: csrfToken });
        const endpoint = document.querySelector('meta[name="analytics-event-url"]')?.content;
        if (!endpoint) return;

        if (isExit && typeof navigator !== 'undefined' && navigator.sendBeacon) {
            try {
                const blob = new Blob([payloadString], { type: 'application/json' });
                const success = navigator.sendBeacon(endpoint, blob);
                if (success) return;
            } catch (e) {
                // Fallback to fetch keepalive
            }
        }

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: payloadString,
            keepalive: isExit,
        }).catch(function () {
            // Silently handle network drops
        });
    }

    // Dwell Time Tracking State
    let activeDwellSection = null;
    let accruedDwellSeconds = 0;
    let dwellIntervalId = null;
    let heartbeatIntervalId = null;

    function commitCurrentDwell() {
        if (activeDwellSection && accruedDwellSeconds > 0) {
            enqueueEvent('section_dwell', {
                section_id: activeDwellSection,
                page_template: pageTemplate,
                dwell_seconds: accruedDwellSeconds,
            });
            accruedDwellSeconds = 0;
        }
    }

    function evaluateActiveSection() {
        if (document.visibilityState === 'hidden') {
            return null;
        }

        let highestRatio = -1;
        let chosenSection = null;

        // Deterministic Dwell Ownership:
        // Highest intersectionRatio >= 0.5; break ties deterministically by DOM order
        for (const [sectionId, info] of observedSections.entries()) {
            if (info.ratio >= 0.5) {
                if (info.ratio > highestRatio) {
                    highestRatio = info.ratio;
                    chosenSection = sectionId;
                }
            }
        }

        return chosenSection;
    }

    function tickDwell() {
        if (document.visibilityState === 'hidden') return;

        const currentActive = evaluateActiveSection();

        if (currentActive !== activeDwellSection) {
            // Section switched: commit previous dwell
            commitCurrentDwell();
            activeDwellSection = currentActive;
            accruedDwellSeconds = currentActive ? 1 : 0;
        } else if (activeDwellSection) {
            accruedDwellSeconds += 1;
            // Periodically commit accumulated dwell every 30 seconds
            if (accruedDwellSeconds >= 30) {
                commitCurrentDwell();
            }
        }
    }

    function startTimers() {
        if (!dwellIntervalId) {
            dwellIntervalId = setInterval(tickDwell, 1000);
        }
        if (!heartbeatIntervalId) {
            // 45-second heartbeat interval (below the 60s activity threshold)
            heartbeatIntervalId = setInterval(function () {
                if (document.visibilityState === 'visible') {
                    commitCurrentDwell();
                    enqueueEvent('session_activity');
                    flushQueue();
                }
            }, 45000);
        }
    }

    function pauseTimers() {
        if (dwellIntervalId) {
            clearInterval(dwellIntervalId);
            dwellIntervalId = null;
        }
        if (heartbeatIntervalId) {
            clearInterval(heartbeatIntervalId);
            heartbeatIntervalId = null;
        }
    }

    function setupObservers() {
        const trackedElements = document.querySelectorAll(
            '[data-section-id], #hero, #pricing, #curriculum, #tutor-bio'
        );

        if (trackedElements.length === 0 || typeof IntersectionObserver === 'undefined') {
            return;
        }

        const observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    const el = entry.target;
                    const sectionId = el.getAttribute('data-section-id') || el.id;
                    if (!sectionId) return;

                    const ratio = entry.intersectionRatio;
                    observedSections.set(sectionId, {
                        ratio: ratio,
                        element: el,
                    });

                    // Section View Firing Rule: strictly once per pageview when >= 50% visible
                    if (ratio >= 0.5 && !viewedSections.has(sectionId)) {
                        viewedSections.add(sectionId);
                        enqueueEvent('section_view', {
                            section_id: sectionId,
                            page_template: pageTemplate,
                        });
                    }
                });
            },
            {
                threshold: [0.0, 0.25, 0.5, 0.75, 1.0],
            }
        );

        trackedElements.forEach(function (el) {
            const sectionId = el.getAttribute('data-section-id') || el.id;
            if (sectionId) {
                observedSections.set(sectionId, { ratio: 0, element: el });
                observer.observe(el);
            }
        });
    }

    // Lifecycle Listeners
    function handleVisibilityChange() {
        if (document.visibilityState === 'hidden') {
            pauseTimers();
            commitCurrentDwell();
            flushQueue(true);
        } else if (document.visibilityState === 'visible') {
            startTimers();
        }
    }

    function handlePageHide() {
        pauseTimers();
        commitCurrentDwell();
        flushQueue(true);
    }

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('pagehide', handlePageHide);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setupPageEvents();
            setupObservers();
            startTimers();
        });
    } else {
        setupPageEvents();
        setupObservers();
        startTimers();
    }
})();
