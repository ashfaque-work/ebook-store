<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Bookmark, List, Type, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatPrice } from '@/lib/money';
import { useReaderSettings } from '@/Reader/useReaderSettings';
import { useProgressSync } from '@/Reader/useProgressSync';

const props = defineProps({
    book: Object,
    assetUrl: String,
    isSample: Boolean,
    progress: { type: Object, default: null },
    bookmarks: { type: Array, default: () => [] },
    highlights: { type: Array, default: () => [] },
});

const viewport = ref(null);
const engine = ref(null);
const loading = ref(true);
const error = ref(null);

const location = ref(props.progress?.location ?? null);
const percent = ref(props.progress?.percent ?? 0);
const chapter = ref(null);
const atEnd = ref(false);
const toc = ref([]);

const showToc = ref(false);
const showSettings = ref(false);
const chromeVisible = ref(true);
const showSampleEnd = ref(false);

const {
    settings,
    resolved,
    themeNames,
    biggerText,
    smallerText,
    looserLines,
    tighterLines,
    widerPage,
    narrowerPage,
    canGrow,
    canShrink,
} = useReaderSettings();

const sync = useProgressSync(props.isSample ? null : route('reader.progress', props.book.id));

/* ------------------------------------------------------------------ chrome */

let chromeTimer = null;

const revealChrome = () => {
    chromeVisible.value = true;
    clearTimeout(chromeTimer);
    // The page is the interface; the controls step out of the way once you
    // settle into reading, and come back the moment you look for them.
    chromeTimer = setTimeout(() => {
        if (!showToc.value && !showSettings.value) {
            chromeVisible.value = false;
        }
    }, 3000);
};

/* ----------------------------------------------------------------- reading */

const onRelocate = (info) => {
    location.value = info.location ?? location.value;
    percent.value = info.percent ?? percent.value;
    chapter.value = info.chapter ?? chapter.value;
    atEnd.value = Boolean(info.atEnd);

    if (props.isSample && info.atEnd) {
        showSampleEnd.value = true;
    }

    if (!props.isSample && location.value) {
        sync.record({ location: location.value, percent: percent.value });
    }
};

const next = () => {
    engine.value?.next();
    revealChrome();
};

const prev = () => {
    engine.value?.prev();
    revealChrome();
};

const goTo = (href) => {
    engine.value?.goTo(href);
    showToc.value = false;
    revealChrome();
};

/* --------------------------------------------------------------- lifecycle */

onMounted(async () => {
    try {
        const factory =
            props.book.format === 'pdf'
                ? (await import('@/Reader/pdfEngine')).createPdfEngine
                : (await import('@/Reader/epubEngine')).createEpubEngine;

        engine.value = await factory({
            url: props.assetUrl,
            element: viewport.value,
            onRelocate,
            onReady: ({ toc: contents }) => (toc.value = contents ?? []),
        });

        engine.value.applyTheme(resolved.value);
        await engine.value.display(location.value);

        engine.value.onSelected(({ location: at, text }) => {
            if (!props.isSample) {
                saveHighlight(at, text);
            }
        });

        props.highlights.forEach((h) => engine.value.highlight(h.location, h.color));
    } catch (e) {
        error.value =
            'This book could not be opened. Try reloading; if it keeps happening, tell us and we will fix the file.';
    } finally {
        loading.value = false;
        revealChrome();
    }

    window.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    clearTimeout(chromeTimer);
    window.removeEventListener('keydown', onKey);
    engine.value?.destroy();
});

watch(resolved, (value) => engine.value?.applyTheme(value), { deep: true });

/* --------------------------------------------------------------- shortcuts */

function onKey(event) {
    if (event.metaKey || event.ctrlKey || event.altKey) return;

    const actions = {
        ArrowRight: next,
        ArrowDown: next,
        PageDown: next,
        j: next,
        ' ': next,
        ArrowLeft: prev,
        ArrowUp: prev,
        PageUp: prev,
        k: prev,
        t: () => (showToc.value = !showToc.value),
        b: () => toggleBookmark(),
        Escape: () => {
            showToc.value = false;
            showSettings.value = false;
            revealChrome();
        },
    };

    const action = actions[event.key];

    if (action) {
        event.preventDefault();
        action();
    }
}

/* ------------------------------------------------------------------ tap and swipe */

const onViewportClick = (event) => {
    const third = viewport.value.clientWidth / 3;
    const x = event.clientX - viewport.value.getBoundingClientRect().left;

    if (x < third) return prev();
    if (x > third * 2) return next();

    chromeVisible.value ? (chromeVisible.value = false) : revealChrome();
};

let touchStartX = null;

const onTouchStart = (e) => (touchStartX = e.changedTouches[0].clientX);

const onTouchEnd = (e) => {
    if (touchStartX === null) return;
    const delta = e.changedTouches[0].clientX - touchStartX;
    touchStartX = null;

    if (Math.abs(delta) > 50) {
        delta < 0 ? next() : prev();
    }
};

/* ----------------------------------------------------------- annotations */

const isBookmarked = computed(() => props.bookmarks.some((b) => b.location === location.value));

const toggleBookmark = () => {
    if (props.isSample || !location.value) return;

    const existing = props.bookmarks.find((b) => b.location === location.value);

    if (existing) {
        router.delete(route('reader.bookmarks.destroy', [props.book.id, existing.id]), {
            preserveScroll: true,
            preserveState: true,
            only: ['bookmarks'],
        });
    } else {
        router.post(
            route('reader.bookmarks.store', props.book.id),
            {
                location: location.value,
                label: chapter.value,
            },
            { preserveScroll: true, preserveState: true, only: ['bookmarks'] },
        );
    }

    revealChrome();
};

const saveHighlight = (at, text) => {
    router.post(
        route('reader.highlights.store', props.book.id),
        {
            location: at,
            text: text.slice(0, 5000),
            color: '#F0A830',
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['highlights'],
            onSuccess: () => engine.value?.highlight(at, '#F0A830'),
        },
    );
};

/* ---------------------------------------------------------------- seeking */

const seeking = ref(false);
const seekDraft = ref(0);

// While a drag is in progress the thumb follows the finger, not the book —
// otherwise it snaps back to the current page on every frame.
const seekValue = computed(() => (seeking.value ? seekDraft.value : percent.value));

const onSeekInput = (event) => {
    seeking.value = true;
    seekDraft.value = Number(event.target.value);
    revealChrome();
};

// On release, not on every input: rendering a new location is expensive, and
// doing it per pixel of drag makes the whole reader stutter.
const onSeekCommit = (event) => {
    const target = Number(event.target.value);
    seeking.value = false;
    engine.value?.goToPercent?.(target / 100);
    revealChrome();
};

/* ---------------------------------------------------------------- reading time */

const timeLeft = computed(() => {
    if (!props.book.page_count || percent.value >= 99) return null;

    // ~300 words a page at ~250 words a minute. A rough number that is still
    // more useful than a percentage.
    const minutes = Math.round(((100 - percent.value) / 100) * props.book.page_count * 1.2);

    if (minutes < 1) return 'less than a minute left';
    if (minutes < 60) return `about ${minutes} min left`;

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return `about ${hours}h ${rest ? `${rest}m ` : ''}left`;
});
</script>

<template>
    <Head :title="`Reading ${book.title}`" />

    <div
        class="reader"
        :style="{ background: resolved.background, color: resolved.foreground }"
        @mousemove="revealChrome"
    >
        <!-- Top bar -->
        <header
            class="bar top"
            :class="{ hidden: !chromeVisible }"
            :style="{ background: resolved.background, borderColor: resolved.muted + '33' }"
        >
            <Link :href="isSample ? `/books/${book.slug}` : '/library'" class="icon" aria-label="Leave the reader">
                <ArrowLeft class="glyph" />
            </Link>

            <p class="chapter" :style="{ color: resolved.muted }">
                <span v-if="isSample" class="badge">Sample</span>
                {{ chapter || book.title }}
            </p>

            <div class="actions">
                <button
                    v-if="!isSample"
                    type="button"
                    class="icon"
                    @click="toggleBookmark"
                    :aria-pressed="isBookmarked"
                    :aria-label="isBookmarked ? 'Remove bookmark' : 'Add bookmark'"
                >
                    <Bookmark class="glyph" :fill="isBookmarked ? 'currentColor' : 'none'" />
                </button>
                <button type="button" class="icon" @click="showToc = !showToc" aria-label="Contents">
                    <List class="glyph" />
                </button>
                <button type="button" class="icon" @click="showSettings = !showSettings" aria-label="Reading settings">
                    <Type class="glyph" />
                </button>
            </div>
        </header>

        <!-- The page -->
        <div
            ref="viewport"
            class="viewport"
            @click="onViewportClick"
            @touchstart.passive="onTouchStart"
            @touchend.passive="onTouchEnd"
        />

        <p v-if="loading" class="notice" :style="{ color: resolved.muted }">Opening the book…</p>
        <p v-else-if="error" role="alert" class="notice">{{ error }}</p>

        <!-- Bottom bar -->
        <footer
            class="bar bottom"
            :class="{ hidden: !chromeVisible }"
            :style="{ background: resolved.background, borderColor: resolved.muted + '33' }"
        >
            <!--
              Draggable, because a progress bar you cannot move is a progress
              bar that makes you scroll a chapter at a time to find the bit you
              half remember. A range input rather than a custom control: it
              arrives with keyboard support and a real thumb on touch.
            -->
            <label class="sr-only" :for="`seek-${book.id}`">Position in the book</label>
            <input
                :id="`seek-${book.id}`"
                class="seek"
                type="range"
                min="0"
                max="100"
                step="1"
                :value="seekValue"
                :style="{ '--seek': `${seekValue}%` }"
                :aria-valuetext="`${seekValue}% through the book`"
                @input="onSeekInput"
                @change="onSeekCommit"
                @pointerdown="seeking = true"
                @keydown.stop
                @click.stop
            />
            <p class="meta" :style="{ color: resolved.muted }">
                <span>{{ seekValue }}%</span>
                <span v-if="seeking">release to jump</span>
                <span v-else-if="timeLeft">{{ timeLeft }}</span>
            </p>
        </footer>

        <!-- Contents -->
        <aside v-if="showToc" class="drawer" :style="{ background: resolved.background }">
            <div class="drawer-head">
                <h2>Contents</h2>
                <button type="button" class="icon" @click="showToc = false" aria-label="Close contents">
                    <X class="glyph" />
                </button>
            </div>

            <nav v-if="toc.length" aria-label="Contents">
                <button v-for="item in toc" :key="item.href" type="button" class="toc-item" @click="goTo(item.href)">
                    {{ item.label }}
                </button>
            </nav>
            <p v-else class="empty" :style="{ color: resolved.muted }">This book has no table of contents.</p>

            <template v-if="bookmarks.length">
                <h2 class="mt">Bookmarks</h2>
                <button
                    v-for="mark in bookmarks"
                    :key="mark.id"
                    type="button"
                    class="toc-item"
                    @click="goTo(mark.location)"
                >
                    {{ mark.label || 'Bookmark' }}
                </button>
            </template>
        </aside>

        <!-- Appearance -->
        <aside v-if="showSettings" class="panel" :style="{ background: resolved.background }">
            <div class="drawer-head">
                <h2>Reading</h2>
                <button type="button" class="icon" @click="showSettings = false" aria-label="Close settings">✕</button>
            </div>

            <div class="row">
                <button
                    v-for="option in themeNames"
                    :key="option.value"
                    type="button"
                    class="chip"
                    :class="{ on: settings.theme === option.value }"
                    @click="settings.theme = option.value"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="row">
                <span class="row-label">Text size</span>
                <button type="button" class="chip" :disabled="!canShrink" @click="smallerText">A&minus;</button>
                <button type="button" class="chip" :disabled="!canGrow" @click="biggerText">A+</button>
            </div>

            <div class="row">
                <span class="row-label">Line spacing</span>
                <button type="button" class="chip" @click="tighterLines">Tighter</button>
                <button type="button" class="chip" @click="looserLines">Looser</button>
            </div>

            <div class="row">
                <span class="row-label">Page width</span>
                <button type="button" class="chip" @click="narrowerPage">Narrower</button>
                <button type="button" class="chip" @click="widerPage">Wider</button>
            </div>

            <label v-if="book.format === 'epub'" class="row check">
                <input type="checkbox" v-model="settings.useBookFont" />
                <span>Use the book's own typeface</span>
            </label>

            <p class="hint" :style="{ color: resolved.muted }">
                Arrow keys or space to turn the page.
                <kbd>t</kbd>
                for contents,
                <kbd>b</kbd>
                to bookmark.
            </p>
        </aside>

        <!-- End of the free sample -->
        <div v-if="showSampleEnd" class="sample-end" :style="{ background: resolved.background }">
            <h2>That's the end of the sample.</h2>
            <p :style="{ color: resolved.muted }">
                {{ book.title }}{{ book.author ? ` by ${book.author}` : '' }} continues from here.
            </p>
            <div class="sample-actions">
                <Link :href="`/books/${book.slug}`" class="buy">
                    Buy and keep reading &middot; {{ formatPrice(book.price_paise) }}
                </Link>
                <button type="button" class="again" :style="{ color: resolved.muted }" @click="showSampleEnd = false">
                    Keep looking at the sample
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.reader {
    position: fixed;
    inset: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-family: Literata, Georgia, serif;
}

.viewport {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 3.5rem 1rem 3.5rem;
}

.bar {
    position: absolute;
    left: 0;
    right: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 1rem;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 0.875rem;
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.top {
    top: 0;
    border-bottom: 1px solid;
}
.bottom {
    bottom: 0;
    flex-direction: column;
    align-items: stretch;
    gap: 0.375rem;
    border-top: 1px solid;
}

.bar.hidden {
    opacity: 0;
    pointer-events: none;
}
.top.hidden {
    transform: translateY(-100%);
}
.bottom.hidden {
    transform: translateY(100%);
}

.chapter {
    flex: 1;
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

.badge {
    margin-right: 0.5rem;
    border-radius: 999px;
    background: #f0a830;
    color: #12172b;
    padding: 0.1rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.actions {
    display: flex;
    gap: 0.25rem;
}

.icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    min-height: 2.25rem;
    border-radius: 8px;
    background: none;
    border: 0;
    color: inherit;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
}

.icon:hover {
    background: rgba(128, 128, 128, 0.15);
}
.icon:focus-visible,
.chip:focus-visible,
.toc-item:focus-visible {
    outline: 2px solid #f0a830;
    outline-offset: 2px;
}

.glyph {
    width: 1.125rem;
    height: 1.125rem;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* The scrubber. Track filled to the left of the thumb, so position reads at a
   glance the way a progress bar does. */
.seek {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 1.25rem;
    background: transparent;
    cursor: pointer;
}

.seek::-webkit-slider-runnable-track {
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(
        to right,
        #f0a830 0%,
        #f0a830 var(--seek, 0%),
        rgba(128, 128, 128, 0.28) var(--seek, 0%),
        rgba(128, 128, 128, 0.28) 100%
    );
}

.seek::-moz-range-track {
    height: 3px;
    border-radius: 999px;
    background: rgba(128, 128, 128, 0.28);
}

.seek::-moz-range-progress {
    height: 3px;
    border-radius: 999px;
    background: #f0a830;
}

.seek::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 0.875rem;
    height: 0.875rem;
    margin-top: -0.3125rem;
    border-radius: 999px;
    background: #f0a830;
    border: 2px solid rgba(0, 0, 0, 0.25);
}

.seek::-moz-range-thumb {
    width: 0.875rem;
    height: 0.875rem;
    border-radius: 999px;
    background: #f0a830;
    border: 2px solid rgba(0, 0, 0, 0.25);
}

.seek:focus-visible {
    outline: 2px solid #f0a830;
    outline-offset: 4px;
}

.track {
    height: 3px;
    border-radius: 999px;
    overflow: hidden;
}
.fill {
    height: 100%;
    background: #f0a830;
    transition: width 0.2s ease;
}

.meta {
    display: flex;
    justify-content: space-between;
    margin: 0;
    font-size: 0.75rem;
}

.notice {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    margin: 0;
    padding: 2rem;
    text-align: center;
    font-family: ui-sans-serif, system-ui, sans-serif;
}

.drawer,
.panel {
    position: absolute;
    top: 0;
    bottom: 0;
    z-index: 30;
    width: min(22rem, 88vw);
    padding: 1rem;
    overflow-y: auto;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.25);
    font-family: ui-sans-serif, system-ui, sans-serif;
}

.drawer {
    left: 0;
}
.panel {
    right: 0;
}

.drawer-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.drawer-head h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}
.mt {
    margin-top: 1.5rem;
    font-size: 1rem;
    font-weight: 600;
}

.toc-item {
    display: block;
    width: 100%;
    padding: 0.5rem 0.25rem;
    border: 0;
    background: none;
    color: inherit;
    text-align: left;
    font-size: 0.875rem;
    line-height: 1.4;
    cursor: pointer;
    border-radius: 6px;
}

.toc-item:hover {
    background: rgba(128, 128, 128, 0.12);
}
.empty {
    font-size: 0.875rem;
}

.row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}
.row-label {
    font-size: 0.8125rem;
    opacity: 0.7;
    flex: 1;
}
.check {
    cursor: pointer;
    font-size: 0.875rem;
}

.chip {
    border: 1px solid rgba(128, 128, 128, 0.4);
    border-radius: 999px;
    background: none;
    color: inherit;
    padding: 0.3rem 0.75rem;
    font-size: 0.8125rem;
    cursor: pointer;
}

.chip.on {
    background: #f0a830;
    border-color: #f0a830;
    color: #12172b;
    font-weight: 600;
}
.chip:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.hint {
    margin-top: 1.5rem;
    font-size: 0.75rem;
    line-height: 1.6;
}
kbd {
    border: 1px solid currentColor;
    border-radius: 4px;
    padding: 0 0.25rem;
    font-size: 0.7rem;
}

.sample-end {
    position: absolute;
    inset: 0;
    z-index: 40;
    display: grid;
    place-content: center;
    gap: 0.75rem;
    padding: 2rem;
    text-align: center;
    font-family: ui-sans-serif, system-ui, sans-serif;
}

.sample-end h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}
.sample-end p {
    margin: 0;
    max-width: 36ch;
}
.sample-actions {
    display: grid;
    gap: 0.75rem;
    justify-items: center;
    margin-top: 0.5rem;
}

.buy {
    display: inline-block;
    border-radius: 10px;
    background: #f0a830;
    color: #12172b;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    text-decoration: none;
}

.again {
    background: none;
    border: 0;
    font-size: 0.875rem;
    cursor: pointer;
    text-decoration: underline;
}

@media (prefers-reduced-motion: reduce) {
    .bar,
    .fill {
        transition: none;
    }
}
</style>
