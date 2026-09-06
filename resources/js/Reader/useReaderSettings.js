import { computed, reactive, watch } from 'vue';

/**
 * Reader appearance.
 *
 * Three surfaces rather than palette variants: the store is ink, the page is
 * paper, and crossing from one to the other is a material change. All three
 * clear 4.5:1 on body text.
 */
export const THEMES = {
    paper: { label: 'Paper', background: '#FCFBF7', foreground: '#1A1D2B', muted: '#5C6274' },
    sepia: { label: 'Sepia', background: '#F4ECD8', foreground: '#3B3126', muted: '#6B5B45' },
    night: { label: 'Night', background: '#14161C', foreground: '#D6D3CD', muted: '#8A8F9C' },
};

const FONT_SIZES = [16, 18, 20, 23, 26];
const LINE_HEIGHTS = [1.5, 1.75, 2.0];
const WIDTHS = [52, 66, 84];

const STORAGE_KEY = 'reader.settings';

const defaults = {
    theme: 'paper',
    fontStep: 1,
    lineStep: 1,
    widthStep: 1,
    useBookFont: false,
};

function load() {
    try {
        return { ...defaults, ...JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') };
    } catch {
        // Private browsing, cleared site data, a browser blocking storage —
        // all fine, the defaults are perfectly readable.
        return { ...defaults };
    }
}

export function useReaderSettings() {
    const settings = reactive(load());

    watch(settings, () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
        } catch {
            // Not worth telling anyone about.
        }
    }, { deep: true });

    const theme = computed(() => THEMES[settings.theme] ?? THEMES.paper);

    const resolved = computed(() => ({
        background: theme.value.background,
        foreground: theme.value.foreground,
        muted: theme.value.muted,
        fontSize: FONT_SIZES[settings.fontStep],
        lineHeight: LINE_HEIGHTS[settings.lineStep],
        maxWidth: WIDTHS[settings.widthStep],
        fontFamily: settings.useBookFont ? null : 'Literata, Georgia, serif',
    }));

    const step = (key, direction, scale) => {
        settings[key] = Math.min(Math.max(0, settings[key] + direction), scale.length - 1);
    };

    return {
        settings,
        theme,
        resolved,
        themeNames: Object.entries(THEMES).map(([value, meta]) => ({ value, label: meta.label })),
        biggerText: () => step('fontStep', 1, FONT_SIZES),
        smallerText: () => step('fontStep', -1, FONT_SIZES),
        looserLines: () => step('lineStep', 1, LINE_HEIGHTS),
        tighterLines: () => step('lineStep', -1, LINE_HEIGHTS),
        widerPage: () => step('widthStep', 1, WIDTHS),
        narrowerPage: () => step('widthStep', -1, WIDTHS),
        canGrow: computed(() => settings.fontStep < FONT_SIZES.length - 1),
        canShrink: computed(() => settings.fontStep > 0),
    };
}
