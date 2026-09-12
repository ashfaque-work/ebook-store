<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * A row of books on a shelf, not a grid of cards. Snap-scrolls with the next
 * cover half visible, which tells a person the row scrolls better than any
 * arrow button does — the arrows are for pointer users who would otherwise
 * have to find a scrollbar.
 */
defineProps({
    heading: { type: String, required: true },
    seeAllHref: { type: String, default: null },
});

const track = ref(null);
const atStart = ref(true);
const atEnd = ref(true);

const readEdges = () => {
    const el = track.value;

    if (!el) return;

    atStart.value = el.scrollLeft <= 4;
    // A pixel of slack: sub-pixel widths mean scrollLeft rarely lands exactly.
    atEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 4;
};

// Roughly a screen at a time, never past the end of the row.
const glide = (direction) => {
    const el = track.value;

    if (!el) return;

    el.scrollBy({ left: direction * el.clientWidth * 0.8, behavior: 'smooth' });
};

let observer = null;

onMounted(async () => {
    await nextTick();
    readEdges();

    // The row's width changes with the viewport, and its content arrives late
    // on deferred shelves — both need the arrows re-evaluated.
    if (typeof ResizeObserver === 'function' && track.value) {
        observer = new ResizeObserver(readEdges);
        observer.observe(track.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section class="mt-14 first:mt-0">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 class="text-xl">{{ heading }}</h2>

            <div class="flex shrink-0 items-center gap-3">
                <Link v-if="seeAllHref" :href="seeAllHref" class="text-accent-text text-sm hover:underline">
                    See all
                </Link>

                <!--
                  Hidden from assistive tech and from touch: the row is already
                  reachable by swiping and by keyboard, so these would only be
                  a second route to somewhere you can already get.
                -->
                <div class="hidden items-center gap-1.5 md:flex" aria-hidden="true">
                    <button type="button" class="shelf-arrow" :disabled="atStart" tabindex="-1" @click="glide(-1)">
                        <ChevronLeft class="size-4" />
                    </button>
                    <button type="button" class="shelf-arrow" :disabled="atEnd" tabindex="-1" @click="glide(1)">
                        <ChevronRight class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <div ref="track" class="shelf" @scroll.passive="readEdges">
            <slot />
        </div>
    </section>
</template>
