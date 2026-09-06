/**
 * PDF rendering, on pdf.js.
 *
 * Lazy-loaded like the EPUB engine, and exposing the same surface so the
 * reader page is format-agnostic.
 *
 * Pages render to a canvas. Text selection — and therefore highlighting — is
 * EPUB-only for now; a PDF text layer is worth adding, but a broken one is
 * worse than an honest absence.
 */
export async function createPdfEngine({ url, element, onRelocate, onReady }) {
    const pdfjs = await import('pdfjs-dist');
    const worker = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');

    pdfjs.GlobalWorkerOptions.workerSrc = worker.default;

    const doc = await pdfjs.getDocument({ url, isEvalSupported: false }).promise;

    const canvas = document.createElement('canvas');
    canvas.style.maxWidth = '100%';
    canvas.style.height = 'auto';
    canvas.style.display = 'block';
    canvas.style.margin = '0 auto';
    element.appendChild(canvas);

    let current = 1;
    let scale = 1;
    let renderTask = null;

    const outline = await doc.getOutline().catch(() => null);

    const toc = await buildToc(doc, outline);

    onReady?.({ toc });

    async function renderPage(pageNumber) {
        current = Math.min(Math.max(1, pageNumber), doc.numPages);

        const page = await doc.getPage(current);

        // Fit the width of the container, then honour the reader's zoom.
        const unscaled = page.getViewport({ scale: 1 });
        const fit = (element.clientWidth || unscaled.width) / unscaled.width;
        const viewport = page.getViewport({ scale: fit * scale * (window.devicePixelRatio || 1) });

        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.style.width = `${viewport.width / (window.devicePixelRatio || 1)}px`;

        renderTask?.cancel();
        renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport });

        try {
            await renderTask.promise;
        } catch {
            // A cancelled render is normal when pages are turned quickly.
        }

        onRelocate?.({
            location: String(current),
            percent: Math.round((current / doc.numPages) * 100),
            chapter: `Page ${current} of ${doc.numPages}`,
            atStart: current === 1,
            atEnd: current === doc.numPages,
        });
    }

    return {
        format: 'pdf',
        toc,

        display: (location) => renderPage(Number.parseInt(location, 10) || 1),
        next: () => renderPage(current + 1),
        prev: () => renderPage(current - 1),
        goTo: (location) => renderPage(Number.parseInt(location, 10) || 1),

        applyTheme({ background, fontSize }) {
            element.style.background = background;
            // A PDF has fixed type, so the size control becomes a zoom.
            const next = fontSize / 18;
            if (next !== scale) {
                scale = next;
                renderPage(current);
            }
        },

        onSelected() {
            // Canvas rendering has no selectable text. See the note above.
        },

        highlight() {},

        currentLocation: () => String(current),

        destroy() {
            renderTask?.cancel();
            doc.destroy();
            canvas.remove();
        },
    };
}

async function buildToc(doc, outline) {
    if (!outline?.length) {
        return [];
    }

    const entries = await Promise.all(
        outline.slice(0, 200).map(async (item) => {
            try {
                const dest = typeof item.dest === 'string' ? await doc.getDestination(item.dest) : item.dest;
                const index = await doc.getPageIndex(dest[0]);

                return { label: item.title, href: String(index + 1) };
            } catch {
                return null;
            }
        }),
    );

    return entries.filter(Boolean);
}
