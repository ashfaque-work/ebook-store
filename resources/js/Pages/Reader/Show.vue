<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Bookmark, ChevronLeft, ChevronRight, List, Type, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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

const stage = ref(null);
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

/* ------------------------------------------------------- the shape of a page
 *
 * A column the full width of a desktop window is 140-odd characters across,
 * which is roughly twice what anyone can read without losing their place on
 * the way back to the left margin. So the frame is capped at the reader's
 * chosen measure and centred, and when there is room for two of them side by
 * side the book opens as a spread — which is, after all, what a book does.
 *
 * It has to be done out here rather than in the theme inside the iframe:
 * epub.js stamps `max-width: inherit !important` on the body in paginated
 * mode, so the only width that survives is the width of the frame it is
 * rendering into.
 */

/** Width of "0" in a serif face, near enough, as a fraction of the size. */
const CH_IN_EM = 0.5;

/** Between the two pages of a spread: a gutter, not a gap. */
const SPREAD_GUTTER = 64;

/** Breathing room outside the text, so the page-turn chips are never on it. */
const SIDE_ROOM = 112;

/** Narrower than this is not a page, whatever the window is doing. */
const MIN_PAGE = 240;

/** The stage's content box: inside its padding, so it is subtracted once only. */
const available = ref(0);

const pageWidth = computed(() => Math.round(resolved.value.maxWidth * resolved.value.fontSize * CH_IN_EM));

const spread = computed(() => available.value >= pageWidth.value * 2 + SPREAD_GUTTER + SIDE_ROOM);

const frameWidth = computed(() =>
    spread.value ? pageWidth.value * 2 + SPREAD_GUTTER : Math.min(pageWidth.value, Math.max(available.value, MIN_PAGE)),
);

let frameObserver = null;

const relayout = () => {
    if (!engine.value) return;
    engine.value.setSpread?.(spread.value);
    engine.value.resize?.();
};

/* ------------------------------------------------------------------ chrome */

let chromeTimer = null;

/*
 * The controls step out of the way once you settle into reading — but not
 * before you have started. Hiding them three seconds after the book opens
 * leaves a first-time reader looking at a page of text with no way back, no
 * contents and no settings, which reads as broken rather than immersive.
 */
const hasTurnedAPage = ref(false);

const revealChrome = () => {
    chromeVisible.value = true;
    clearTimeout(chromeTimer);

    if (!hasTurnedAPage.value) return;

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
    if (showHint.value) dismissHint();
    hasTurnedAPage.value = true;
    engine.value?.next();
    revealChrome();
};

const prev = () => {
    if (showHint.value) dismissHint();
    hasTurnedAPage.value = true;
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
            onMeasured: ({ words }) => (measuredWords.value = words),
        });

        engine.value.applyTheme(resolved.value);
        engine.value.setSpread?.(spread.value);

        // Before the first chapter is displayed, not after: this registers a
        // content hook, and a hook added later only reaches chapters loaded
        // after it. Registered afterwards, the opening chapter — the one
        // everybody meets — was the one chapter you could not tap to turn.
        engine.value.onGesture?.({
            tap: tapAt,
            swipe: (direction) => (direction === 'next' ? next() : prev()),
        });

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
    clearTimeout(relayoutTimer);
    window.removeEventListener('keydown', onKey);
    frameObserver?.disconnect();
    engine.value?.destroy();
});

// The stage, not the window: a phone rotating and a desktop window dragged
// narrower both matter, and so does the drawer opening beside the page.
let relayoutTimer = null;

onMounted(() => {
    if (!stage.value) return;

    // Measured before the book opens, so the first chapter is laid out at the
    // width it will keep. The observer reports a content box, so this has to
    // be one too — reading clientWidth here counted the padding twice and cost
    // a phone 32 pixels of page.
    const style = window.getComputedStyle(stage.value);
    available.value = Math.round(
        stage.value.getBoundingClientRect().width - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
    );

    frameObserver = new ResizeObserver(([entry]) => {
        const width = Math.round(entry.contentRect.width);

        if (width === available.value) return;

        available.value = width;

        // Re-laying out an EPUB means re-rendering the chapter, so it waits
        // for the drag to stop rather than running on every frame of it.
        clearTimeout(relayoutTimer);
        relayoutTimer = setTimeout(relayout, 180);
    });

    frameObserver.observe(stage.value);
});

watch(resolved, (value) => engine.value?.applyTheme(value), { deep: true });

// Text size and measure both change how wide a page should be, and the frame
// has to settle before epub.js is asked to re-flow into it.
watch([pageWidth, spread], async () => {
    await nextTick();
    relayout();
});

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

/**
 * Outer thirds turn the page; the middle shows or hides the controls.
 *
 * Measured on the stage, so the margins either side of a narrow page count as
 * the edges of the screen — which is where a hand actually falls.
 */
const tapAt = (clientX) => {
    if (!stage.value) return;

    const third = stage.value.clientWidth / 3;
    const x = clientX - stage.value.getBoundingClientRect().left;

    if (x < third) return prev();
    if (x > third * 2) return next();

    chromeVisible.value ? (chromeVisible.value = false) : revealChrome();
};

const onViewportClick = (event) => tapAt(event.clientX);

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

/*
 * The page turns by tapping the edges, swiping, or the arrow keys — and none
 * of those announce themselves. Shown once, then remembered, because a hint
 * that returns every session is an irritation rather than help.
 *
 * It stays until the first page is turned rather than fading on a timer: the
 * people who need it are the ones still working out what to do, and they are
 * precisely the ones a six-second timer runs out on.
 */
const HINT_SEEN = 'reader:turn-hint-seen';
const showHint = ref(false);

const dismissHint = () => {
    showHint.value = false;
    try {
        window.localStorage.setItem(HINT_SEEN, '1');
    } catch {
        // Private windows and blocked storage: the hint simply shows again.
    }
};

onMounted(() => {
    let seen = false;
    try {
        seen = window.localStorage.getItem(HINT_SEEN) === '1';
    } catch {
        seen = false;
    }

    if (seen) return;

    showHint.value = true;
});

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

/*
 * Measured from the file that is open, not from the catalogue.
 *
 * The catalogue knows Pride and Prejudice is 439 pages, and a sample of it is
 * six thousand words — so reading the page count told anyone opening a sample
 * they had "about 8h 47m left" of a chapter they would finish over a coffee.
 * The engines count what they actually loaded and report it here; the page
 * count is only a fallback for a book whose index has not finished building.
 */
const WORDS_A_MINUTE = 250;
const WORDS_A_PAGE = 300;

const measuredWords = ref(null);

const totalWords = computed(() => {
    if (measuredWords.value) return measuredWords.value;

    // No fallback for a sample: a wrong estimate is worse than none.
    return props.isSample ? null : (props.book.page_count ?? 0) * WORDS_A_PAGE || null;
});

const timeLeft = computed(() => {
    if (!totalWords.value || percent.value >= 99) return null;

    const minutes = Math.round((((100 - percent.value) / 100) * totalWords.value) / WORDS_A_MINUTE);

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

        <!--
          The page, and the room around it. The stage takes the taps, because
          the margins either side of the text are the most natural place to
          tap to turn; the frame inside it is capped at the measure and holds
          the rendered book.
        -->
        <div
            ref="stage"
            class="stage"
            @click="onViewportClick"
            @touchstart.passive="onTouchStart"
            @touchend.passive="onTouchEnd"
        >
            <div ref="viewport" class="page-frame" :style="{ width: `${frameWidth}px` }" />
        </div>

        <!--
          The tap zones made visible. Pointer devices get something to aim at
          and, more to the point, something to notice; touch keeps the whole
          edge of the page and the swipe, which is why these are hidden there.
        -->
        <button type="button" class="page-turn left" aria-label="Previous page" @click.stop="prev">
            <ChevronLeft class="turn-glyph" />
        </button>

        <button type="button" class="page-turn right" aria-label="Next page" @click.stop="next">
            <ChevronRight class="turn-glyph" />
        </button>

        <p v-if="showHint" class="turn-hint" @click="dismissHint">
            Tap either side of the page to turn it — or swipe, or use
            <kbd>&larr;</kbd>
            <kbd>&rarr;</kbd>
        </p>

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

.stage {
    flex: 1;
    min-height: 0;
    display: flex;
    justify-content: center;
    overflow: hidden;
    padding: 3.5rem 1rem;
}

.page-frame {
    flex: none; /* the width is computed, not negotiated */
    height: 100%;
    min-height: 0;
    overflow: hidden;
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

/*
  Page turns. Anchored to the edges rather than floated over the text, and —
  unlike the rest of the chrome — they never hide. A control that disappears
  three seconds after the page loads is a control nobody finds, which is
  exactly how a reader ends up asking how to turn the page. Dim enough to stay
  out of the way of the prose, bright on hover.
*/
.page-turn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 15;
    display: none;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 4.5rem;
    border: 0;
    border-radius: 10px;
    background: rgba(128, 128, 128, 0.08);
    color: inherit;
    opacity: 0.55;
    cursor: pointer;
    transition:
        opacity 220ms ease,
        background 220ms ease;
}

@media (hover: hover) and (pointer: fine) {
    .page-turn {
        display: flex;
    }
}

.page-turn:hover {
    opacity: 1;
    background: rgba(128, 128, 128, 0.18);
}

.page-turn.left {
    left: 0.5rem;
}

.page-turn.right {
    right: 0.5rem;
}

.page-turn:focus-visible {
    outline: 2px solid #f0a830;
    outline-offset: 2px;
    opacity: 1;
}

.turn-glyph {
    width: 1.5rem;
    height: 1.5rem;
}

/* Shown once, on the first book anyone opens. */
.turn-hint {
    position: absolute;
    left: 50%;
    bottom: 4.5rem;
    transform: translateX(-50%);
    z-index: 25;
    max-width: min(90vw, 30rem);
    padding: 0.625rem 1rem;
    border-radius: 999px;
    background: rgba(20, 20, 28, 0.88);
    color: #fff;
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 0.8125rem;
    text-align: center;
    cursor: pointer;
    animation: hint-in 420ms ease both;
}

.turn-hint kbd {
    display: inline-block;
    padding: 0 0.3rem;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.16);
    font-family: inherit;
}

@keyframes hint-in {
    from {
        opacity: 0;
        transform: translate(-50%, 8px);
    }
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
