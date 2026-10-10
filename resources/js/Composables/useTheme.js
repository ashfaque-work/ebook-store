import { ref, watchEffect } from 'vue';

/**
 * The shop's ground, and the switch for it.
 *
 * Ink is the default — not the operating system's preference. The store is a
 * dark room where the covers are the only lit things, which is the whole
 * identity and also how you know you are shopping rather than reading; the
 * reader drains the ink out and hands you paper. Following
 * prefers-color-scheme would mean most people never see either decision, only
 * whichever one their phone happened to pick.
 *
 * The class itself is set by a script in the document head, before the first
 * paint. This keeps that in step and remembers a choice once it is made.
 */
const STORAGE_KEY = 'theme';

function stored() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        // Private browsing, or storage blocked outright.
        return null;
    }
}

export function useTheme() {
    const theme = ref(stored() === 'light' ? 'light' : 'dark');

    const toggleTheme = () => {
        theme.value = theme.value === 'light' ? 'dark' : 'light';
    };

    watchEffect(() => {
        document.documentElement.classList.toggle('dark', theme.value === 'dark');

        try {
            localStorage.setItem(STORAGE_KEY, theme.value);
        } catch {
            // The choice lasts for this visit, which is better than failing.
        }
    });

    return { theme, toggleTheme };
}
