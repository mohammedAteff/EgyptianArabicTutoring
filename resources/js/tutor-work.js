function initializeTutorWork() {
    const shell = document.querySelector('[data-work-shell]');
    if (!shell) return;
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const operationId = () => {
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };
    let snapshot = null;
    let receivedAt = performance.now();
    let lastServer = '';
    let unavailableMessage = '';
    let refreshInFlight = false;
    const commandAttempts = new Map();
    const duration = seconds => `${Math.floor(seconds / 3600)}:${String(Math.floor(seconds % 3600 / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    function render() {
        const timer = snapshot?.timer;
        const elapsed = timer ? timer.elapsed_seconds + (timer.lifecycle === 'running' ? Math.floor((performance.now() - receivedAt) / 1000) : 0) : 0;
        const text = unavailableMessage ? 'Saved timer state unavailable. Refresh saved state.' : timer ? `${timer.lifecycle === 'running' ? 'Running' : 'Paused'} · ${timer.category_label} · ${duration(elapsed)}` : 'No open timer';
        shell.querySelector('[data-work-indicator]').textContent = text;
        shell.hidden = !timer && !unavailableMessage;
        document.querySelectorAll('[data-work-timer-state]').forEach(element => { element.textContent = text; });
        document.querySelectorAll('[data-start-form]').forEach(form => { form.hidden = Boolean(timer); });
        document.querySelectorAll('[data-work-commands]').forEach(group => { group.hidden = !timer; });
        document.querySelectorAll('[data-work-command]').forEach(button => { button.hidden = !timer || (button.dataset.workCommand === 'pause' && timer.lifecycle !== 'running') || (button.dataset.workCommand === 'resume' && timer.lifecycle !== 'paused'); });
        document.querySelectorAll('[data-work-warning]').forEach(element => { element.hidden = !timer?.warning; });
        document.querySelectorAll('form[action$="/logout"]').forEach(form => {
            let note = form.querySelector('[data-work-logout-note]');
            if (!note) {
                note = document.createElement('span'); note.dataset.workLogoutNote = ''; note.className = 'block text-xs'; form.append(note);
            }
            note.hidden = !timer;
            note.textContent = unavailableMessage ? 'Saved timing is unknown. Check the workspace before assuming timing stopped.' : timer?.lifecycle === 'running' ? 'The shared timer keeps running after sign out.' : 'The paused shared timer remains saved after sign out.';
        });
    }
    function adopt(state) {
        if (!state) return;
        if (String(state.actor_id) !== shell.dataset.actorId || state.context_version !== shell.dataset.contextVersion || String(state.workspace_id ?? '') !== shell.dataset.workspaceId) {
            unavailableMessage = 'The workspace interface or signed-in account changed. Your text is preserved; refresh the interface for the current account before submitting a new command.';
            render();
            throw new Error(unavailableMessage);
        }
        if (state.activation_pending || state.transition_pending) {
            snapshot = null;
            unavailableMessage = '';
            shell.hidden = true;
            return;
        }
        if (state.sequence < (snapshot?.sequence ?? 0) || (state.sequence === snapshot?.sequence && state.server_at < lastServer)) return;
        if (state.csrf) csrf = state.csrf;
        if (state.server_at === lastServer && state.timer?.id === snapshot?.timer?.id && state.timer?.version < snapshot?.timer?.version) return;
        unavailableMessage = '';
        snapshot = state; lastServer = state.server_at; receivedAt = performance.now();
        shell.querySelector('[data-work-shell-error]').hidden = true;
        render();
    }
    async function readJson(response) {
        if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Sign in again, refresh saved state, then deliberately retry. A failed stop may leave the timer running; you can correct it afterward.');
        return response.json();
    }
    async function refresh() {
        if (refreshInFlight || document.hidden) return;
        refreshInFlight = true;
        try {
            const response = await fetch(shell.dataset.stateUrl, {headers: {Accept: 'application/json'}, cache: 'no-store', credentials: 'same-origin'});
            const data = await readJson(response);
            if (!response.ok) throw new Error(data.message || 'Saved state could not be retrieved.');
            const recovering = Boolean(unavailableMessage);
            adopt(data);
            if (recovering && !unavailableMessage) {
                document.querySelectorAll('[data-work-command-result]').forEach(element => { element.textContent = 'Saved state refreshed.'; });
            }
        } catch (error) {
            unavailableMessage = error.message;
            render();
            shell.hidden = false; shell.querySelector('[data-work-shell-error]').hidden = false;
            document.querySelectorAll('[data-work-command-result]').forEach(element => { element.textContent = error.message; });
        } finally { refreshInFlight = false; }
    }
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('tutor-work-state-invalidated') : null;
    channel?.addEventListener('message', refresh);
    async function send(url, payload, result, controls) {
        controls.forEach(control => { control.disabled = true; });
        result.textContent = 'Pending confirmation…'; result.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {method: 'POST', headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, credentials: 'same-origin', body: JSON.stringify({...payload, actor_context: shell.dataset.actorId, workspace_context: shell.dataset.workspaceId, context_version: shell.dataset.contextVersion})});
            const data = await readJson(response);
            if (data.current) adopt(data.current);
            if (!response.ok) {
                result.textContent = Object.values(data.errors || {}).flat().join(' ') || data.message || 'The operation was not confirmed. Refresh state before retrying.';
                if (response.status === 409) result.textContent += ' Your text is preserved. Review the current record before submitting a new version.';
                if (data.record) result.textContent += ` Current record: version ${data.record.version}, ${data.record.acceptance}.`;
                if ([401, 419].includes(response.status)) result.textContent += ' Sign in again if needed, then use Refresh saved state before deliberately retrying this request. Your text is preserved.';
                return null;
            }
            result.textContent = data.replayed ? 'Recovered: the original operation was already saved.' : 'Saved and confirmed.';
            channel?.postMessage('changed');
            return data;
        } catch (error) {
            result.textContent = `Not confirmed. ${error.message} Your entered text is still here. Retry the same request to recover its committed result.`;
            return null;
        } finally { controls.forEach(control => { control.disabled = false; }); result.removeAttribute('aria-busy'); }
    }
    document.querySelectorAll('[data-work-form]').forEach(form => {
        form.addEventListener('input', () => { form.elements.operation_id.value = operationId(); });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const payload = {};
            for (const [name, value] of new FormData(form)) {
                if (name === '_token') continue;
                const match = name.match(/^intervals\[(\d+)\]\[(\w+)\]$/);
                if (match) {
                    payload.intervals ??= []; payload.intervals[Number(match[1])] ??= {};
                    payload.intervals[Number(match[1])][match[2]] = ['start', 'end'].includes(match[2]) && value.length === 16 ? `${value}:00` : value;
                } else payload[name] = value;
            }
            const data = await send(form.getAttribute('action'), payload, form.querySelector('[data-work-result]'), [...form.querySelectorAll('button')]);
            if (data) {
                form.elements.operation_id.value = operationId();
                form.dispatchEvent(new CustomEvent('admin-form-ready', {bubbles: true}));
                if (payload.action !== 'start') window.location.assign(`${shell.dataset.commandBase}/${data.result.entry_id}`);
            }
        });
    });
    document.querySelectorAll('[data-work-command]').forEach(button => button.addEventListener('click', async () => {
        if (!snapshot?.timer) return;
        const action = button.dataset.workCommand;
        let attempt = commandAttempts.get(action);
        if (!attempt) {
            attempt = {id: snapshot.timer.id, payload: {action, operation_id: operationId(), version: snapshot.timer.version}};
            commandAttempts.set(action, attempt);
        }
        const data = await send(`${shell.dataset.commandBase}/${attempt.id}`, attempt.payload, document.querySelector('[data-work-command-result]'), [...document.querySelectorAll('[data-work-command]')]);
        if (data) {
            commandAttempts.clear();
            if (action === 'stop') {
                const link = document.querySelector('[data-work-last-saved]'); link.hidden = false; link.href = `${shell.dataset.commandBase}/${data.result.entry_id}`;
            }
        }
    }));
    document.querySelectorAll('[data-work-refresh]').forEach(button => button.addEventListener('click', () => { commandAttempts.clear(); refresh(); }));
    document.querySelectorAll('[data-work-placement-form]').forEach(form => {
        const mode = form.querySelector('[data-work-placement]');
        function place() {
            form.querySelectorAll('[data-placement]').forEach(group => {
                group.hidden = group.dataset.placement !== mode.value;
                group.querySelectorAll('input,select,button').forEach(control => { control.disabled = group.hidden; });
            });
        }
        mode.addEventListener('change', place); place();
        const rows = form.querySelector('[data-intervals]');
        function reindex() { [...rows.children].forEach((row, index) => row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/intervals\[\d+\]/, `intervals[${index}]`); })); }
        form.querySelector('[data-add-interval]').addEventListener('click', () => {
            const row = rows.firstElementChild.cloneNode(true); row.querySelectorAll('input').forEach(input => { input.value = ''; }); rows.append(row); reindex();
        });
        rows.addEventListener('click', event => { if (event.target.closest('[data-remove-interval]') && rows.children.length > 1) { event.target.closest('[data-interval-row]').remove(); reindex(); } });
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', refresh);
    setInterval(refresh, 15000);
    setInterval(() => { if (snapshot && !document.hidden) render(); }, 1000);
    refresh();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeTutorWork);
else initializeTutorWork();
