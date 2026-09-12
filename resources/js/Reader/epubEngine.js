/**
 * EPUB rendering, on epub.js.
 *
 * Loaded only from the reader route — epub.js is ~180 KB gzipped and must
 * never enter the storefront bundle.
 *
 * Exposes the same surface as pdfEngine so the reader page does not care
 * which format it is showing.
 */
export async function createEpubEngine({ url, element, onRelocate, onReady }) {
    const { default: ePub } = await import('epubjs');

    // openAs is not optional here. epub.js sniffs the input type from the
    // URL's extension, and our asset routes deliberately have none — the
    // client is never given a file path. Without this it decides the URL is an
    // unzipped EPUB *directory* and goes looking for
    // `<assetUrl>/META-INF/container.xml`, which 404s and leaves the reader
    // stuck on "Opening the book…".
    const book = ePub(url, { openAs: 'epub' });

    const rendition = book.renderTo(element, {
        width: '100%',
        height: '100%',
        flow: 'paginated',
        spread: 'none',
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
                .generate(1600)
                .then(() => {
                    locationsReady = true;
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

        /** epub.js styles the iframe's own document, not our page. */
        applyTheme({ background, foreground, fontSize, lineHeight, fontFamily, maxWidth }) {
            rendition.themes.override('color', foreground, true);
            rendition.themes.override('background', background, true);
            rendition.themes.default({
                body: {
                    'font-size': `${fontSize}px !important`,
                    'line-height': `${lineHeight} !important`,
                    'font-family': fontFamily ? `${fontFamily} !important` : undefined,
                    'max-width': `${maxWidth}ch`,
                    margin: '0 auto',
                    padding: '0 8px',
                    'text-align': 'justify',
                    hyphens: 'auto',
                },
                'p, li': {
                    'font-size': `${fontSize}px !important`,
                    'line-height': `${lineHeight} !important`,
                },
                a: { color: 'inherit' },
                '::selection': { background: 'rgba(240, 168, 48, 0.35)' },
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
