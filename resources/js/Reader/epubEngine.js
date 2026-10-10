/**
 * EPUB rendering, on epub.js.
 *
 * Loaded only from the reader route — epub.js is ~180 KB gzipped and must
 * never enter the storefront bundle.
 *
 * Exposes the same surface as pdfEngine so the reader page does not care
 * which format it is showing.
 */

/** Location granularity. Finer gives a smoother percentage and a slower index. */
const CHARS_PER_LOCATION = 1600;

/** Average English word plus its trailing space. Used only for a time estimate. */
const CHARS_PER_WORD = 5.7;

/** Far enough that it was a swipe and not a tap that moved. */
const SWIPE_THRESHOLD = 50;

/** A spread width threshold low enough to mean "whenever asked for one". */
const SPREAD_ALWAYS = 1;

export async function createEpubEngine({ url, element, onRelocate, onReady, onMeasured }) {
    const { default: ePub } = await import('epubjs');

    // openAs is not optional here. epub.js sniffs the input type from the
    // URL's extension, and our asset routes deliberately have none — the
    // client is never given a file path. Without this it decides the URL is an
    // unzipped EPUB *directory* and goes looking for
    // `<assetUrl>/META-INF/container.xml`, which 404s and leaves the reader
    // stuck on "Opening the book…".
    const book = ePub(url, { openAs: 'epub' });

    // Whether a second column appears is the reader page's decision, not a
    // width threshold in here: it sizes the frame this renders into, and only
    // asks for a spread when two columns of the chosen measure will fit. So
    // minSpreadWidth is pinned to 1 — "whenever asked" — and `spread` is the
    // only switch. Left to itself epub.js splits at a fixed 800px and gives
    // whatever measure that happens to produce.
    const rendition = book.renderTo(element, {
        width: '100%',
        height: '100%',
        flow: 'paginated',
        spread: 'none',
        minSpreadWidth: SPREAD_ALWAYS,
        allowScriptedContent: false,
    });

    let percent = 0;
    let locationsReady = false;

    rendition.on('relocated', (location) => {
        const cfi = location?.start?.cfi ?? null;

        if (locationsReady && cfi) {
            percent = Math.round((book.locations.percentageFromCfi(cfi) || 0) * 100);
        }

        onRelocate?.({
            location: cfi,
            percent,
            chapter: chapterLabel(book, location),
            atStart: Boolean(location?.atStart),
            atEnd: Boolean(location?.atEnd),
        });
    });

    await book.ready;

    const toc = (book.navigation?.toc ?? []).map((item) => ({
        label: item.label?.trim() ?? '',
        href: item.href,
    }));

    onReady?.({ toc });

    return {
        format: 'epub',

        async display(location) {
            await rendition.display(location || undefined);

            // Generating locations is what makes a percentage meaningful, and
            // it is slow on a big book — do it after the first page is on
            // screen rather than making the reader wait for it.
            book.locations
                .generate(CHARS_PER_LOCATION)
                .then(() => {
                    locationsReady = true;

                    // The length of what was actually opened, rather than what
                    // the catalogue says about the whole book. It is the same
                    // number for a full book and the only honest one for a
                    // sample, which used to borrow the book's own 439 pages
                    // and promise eight hours of reading for six thousand
                    // words.
                    const characters = (book.locations.total || 0) * CHARS_PER_LOCATION;

                    if (characters > 0) {
                        onMeasured?.({ words: Math.round(characters / CHARS_PER_WORD) });
                    }

                    const cfi = rendition.currentLocation()?.start?.cfi;
                    if (cfi) {
                        percent = Math.round((book.locations.percentageFromCfi(cfi) || 0) * 100);
                        onRelocate?.({ location: cfi, percent, chapter: null });
                    }
                })
                .catch(() => {
                    // A book we cannot index still reads fine; only the percentage
                    // is lost, so this is not worth surfacing.
                });
        },

        next: () => rendition.next(),
        prev: () => rendition.prev(),
        goTo: (href) => rendition.display(href),

        /**
         * Jump to a fraction of the way through the book.
         *
         * Needs the location index, which is generated in the background after
         * the first page renders. Until it is ready there is no mapping from a
         * percentage to a place in the text, so this does nothing rather than
         * guessing and throwing the reader somewhere arbitrary.
         */
        async goToPercent(fraction) {
            if (!locationsReady) return;

            const clamped = Math.min(Math.max(fraction, 0), 1);
            const cfi = book.locations.cfiFromPercentage(clamped);

            if (cfi) await rendition.display(cfi);
        },

        /**
         * epub.js styles the iframe's own document, not our page.
         *
         * Note what is *not* set here: a max-width on the body. In paginated
         * mode epub.js writes `max-width: inherit !important` onto the body
         * itself, so a measure set here is thrown away — which is why the
         * Narrower/Wider control did nothing at all while lines ran a hundred
         * and forty characters across a desktop. The measure is the width of
         * the frame this renders into, and the reader page owns it.
         */
        applyTheme({ background, foreground, fontSize, lineHeight, fontFamily }) {
            rendition.themes.override('color', foreground, true);
            rendition.themes.override('background', background, true);
            rendition.themes.default({
                body: {
                    'font-size': `${fontSize}px !important`,
                    'line-height': `${lineHeight} !important`,
                    'font-family': fontFamily ? `${fontFamily} !important` : undefined,
                    padding: '0 8px',
                    hyphens: 'auto',
                },
                // Justified prose, but only the prose. Applied to the body it
                // caught the title pages too, and stretched THE HOUND OF THE
                // BASKERVILLES across a phone with rivers between the words.
                // No !important either, so a book that centres or indents its
                // own paragraphs still wins.
                p: { 'text-align': 'justify' },
                'p, li': {
                    'font-size': `${fontSize}px !important`,
                    'line-height': `${lineHeight} !important`,
                },
                a: { color: 'inherit' },
                '::selection': { background: 'rgba(240, 168, 48, 0.35)' },
            });
        },

        /**
         * One column or two.
         *
         * The minimum is passed every time rather than left alone, because
         * epub.js rebuilds its layout object from `settings` whenever the
         * package metadata is re-read, and a minimum that was only ever
         * written to the layout is lost there. It must also be truthy: the
         * setter guards with `if (min)`, so a perfectly sensible 0 is dropped
         * on the floor and the old threshold survives — which is exactly how
         * this first shipped doing nothing at all.
         */
        setSpread(spread) {
            rendition.spread(spread ? 'auto' : 'none', SPREAD_ALWAYS);
        },

        /** The frame changed size. epub.js only watches the window. */
        resize() {
            try {
                rendition.resize();
            } catch {
                // Mid-teardown, or before the first chapter is on screen.
            }
        },

        /**
         * Taps and swipes made *inside* the book.
         *
         * Every chapter renders in its own iframe, and pointer events in an
         * iframe do not reach the page around it. So the tap zones and the
         * swipe were only ever live on the strip of padding at the edge of the
         * stage — a phone, where the page fills the screen, had neither, and
         * the hint telling people to tap or swipe was describing something
         * that did not work.
         *
         * Registered as a content hook because each chapter is a new document.
         */
        onGesture({ tap, swipe }) {
            rendition.hooks.content.register((contents) => {
                const doc = contents.document;
                const frame = doc.defaultView?.frameElement;

                // Into the coordinate space of the page around the iframe, so
                // "left third of the screen" means the same on both sides.
                const toPageX = (x) => x + (frame?.getBoundingClientRect().left ?? 0);

                // A tap that ends a selection is someone highlighting, and a
                // tap on a footnote link is a link.
                const isReading = (event) =>
                    !event.target?.closest?.('a[href]') && !doc.defaultView?.getSelection()?.toString();

                doc.addEventListener(
                    'click',
                    (event) => {
                        if (isReading(event)) tap?.(toPageX(event.clientX));
                    },
                    { passive: true },
                );

                let startX = null;

                doc.addEventListener('touchstart', (event) => (startX = event.changedTouches[0].clientX), {
                    passive: true,
                });

                doc.addEventListener(
                    'touchend',
                    (event) => {
                        if (startX === null) return;

                        const delta = event.changedTouches[0].clientX - startX;
                        startX = null;

                        if (Math.abs(delta) > SWIPE_THRESHOLD && isReading(event)) {
                            swipe?.(delta < 0 ? 'next' : 'prev');
                        }
                    },
                    { passive: true },
                );
            });
        },

        /** The reader page forwards key and swipe events here. */
        onSelected(handler) {
            rendition.on('selected', (cfiRange, contents) => {
                const text = contents.window.getSelection()?.toString()?.trim();
                if (text) {
                    handler({ location: cfiRange, text });
                }
            });
        },

        highlight(location, color) {
            try {
                rendition.annotations.highlight(location, {}, null, 'hl', {
                    fill: color,
                    'fill-opacity': '0.35',
                });
            } catch {
                // A highlight whose location no longer resolves is not fatal.
            }
        },

        currentLocation: () => rendition.currentLocation()?.start?.cfi ?? null,

        destroy() {
            try {
                rendition.destroy();
                book.destroy();
            } catch {
                // Already torn down.
            }
        },

        toc,
    };
}

function chapterLabel(book, location) {
    const href = location?.start?.href;
    if (!href) return null;

    const item = book.navigation?.toc?.find((entry) => entry.href === href || entry.href?.split('#')[0] === href);

    return item?.label?.trim() ?? null;
}
