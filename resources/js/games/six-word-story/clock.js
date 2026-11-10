/** A pausable clock; hidden time never consumes a prompt interval. */
export function createPromptClock(seconds, now = 0) {
    let remaining = seconds * 1000, last = now;
    return {
        tick(time, active = true) {
            if (active) remaining = Math.max(0, remaining - Math.max(0, time - last));
            last = time;
            return remaining;
        },
        next(duration, time) { remaining = duration * 1000; last = time; },
    };
}
