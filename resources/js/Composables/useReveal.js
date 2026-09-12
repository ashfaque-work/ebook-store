import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Reveal an element the first time it is scrolled into view.
 *
 * A directive rather than a scroll listener: IntersectionObserver does this
 * work off the main thread, so a long shelf of covers does not cost a frame
 * every time the page moves.
 *
 * Anything that fades in must start visible and be hidden by script, never the
 * reverse — otherwise a reader with JavaScript blocked, or an observer that
 * never fires, gets a blank page instead of a plain one.
 */
export function useReveal() {
    const root = ref(null);
    let observer = null;

    onMounted(() => {
        const el = root.value;

        if (!el) return;

        const reducedMotion =
            typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Asking for less motion is not asking for less content.
        if (reducedMotion || typeof IntersectionObserver !== 'function') {
            el.classList.add('is-revealed');
            return;
        }

        el.classList.add('will-reveal');

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                });
            },
            // A little before it arrives, so the movement has finished by the
            // time the element is properly in view.
            { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
        );

        observer.observe(el);
    });

    onBeforeUnmount(() => observer?.disconnect());

    return { root };
}
