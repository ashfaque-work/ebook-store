import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Save where the reader has got to, without writing on every page turn.
 *
 * A fast reader turns a page every few seconds; writing each one would be
 * hundreds of requests an hour for a value nobody reads until the next
 * session. So: throttle to one write per interval, and flush on the way out.
 */
export function useProgressSync(url, { intervalMs = 10000 } = {}) {
    let pending = null;
    let timer = null;
    let lastSent = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const send = (payload, keepalive = false) => {
        if (!payload || !url) return;

        const serialised = JSON.stringify(payload);
        if (serialised === lastSent) return;
        lastSent = serialised;

        // keepalive lets the last write survive the page being closed, which
        // is exactly the write that matters most.
        fetch(url, {
            method: 'POST',
            keepalive,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: serialised,
        }).catch(() => {
            // Losing a position is a small annoyance, never an error worth
            // interrupting someone's reading for.
        });
    };

    const flush = (keepalive = false) => {
        clearTimeout(timer);
        timer = null;
        send(pending, keepalive);
    };

    /** Call on every relocation; the throttle decides what actually goes out. */
    const record = (payload) => {
        pending = payload;

        if (!timer) {
            timer = setTimeout(() => flush(false), intervalMs);
        }
    };

    const onHidden = () => {
        if (document.visibilityState === 'hidden') {
            flush(true);
        }
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', onHidden);
        window.addEventListener('pagehide', () => flush(true));
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onHidden);
        flush(true);
    });

    return { record, flush };
}
