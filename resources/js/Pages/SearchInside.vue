<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BookCover from '@/Components/BookCover.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatPrice } from '@/lib/money';

const props = defineProps({
    query: { type: String, default: '' },
    book: { type: Object, default: null },
    results: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    indexedBooks: { type: Number, default: 0 },
});

const term = ref(props.query);
const searching = ref(false);

const run = () => {
    router.get(
        '/search-inside',
        { q: term.value || undefined, book: props.book?.slug || undefined },
        {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            onStart: () => (searching.value = true),
            onFinish: () => (searching.value = false),
        },
    );
};

let timer = null;
watch(term, () => {
    clearTimeout(timer);
    timer = setTimeout(run, 350);
});

const hasSearched = computed(() => props.query !== '');

/**
 * Wrap the searched words in a mark so the eye lands on them. Built from text
 * the server sent, and escaped here rather than trusted — the passage is book
 * text, but the query came from the address bar.
 */
const escapeHtml = (value) =>
    value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const highlighted = (excerpt) => {
    const safe = escapeHtml(excerpt);
    const words = props.query
        .replace(/["']/g, ' ')
        .split(/\s+/)
        .filter((word) => word.length > 2)
        .map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

    if (!words.length) return safe;

    return safe.replace(new RegExp(`(${words.join('|')})`, 'gi'), '<mark>$1</mark>');
};
</script>

<template>
    <Head :title="query ? `“${query}” inside the books` : 'Search inside the books'" />

    <GuestLayout>
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl">
                <template v-if="book">Search inside {{ book.title }}</template>
                <template v-else>Search inside the books</template>
            </h1>

            <p class="text-muted measure mt-2 text-sm">
                <template v-if="book">Every word of this book, not just its description.</template>
                <template v-else>
                    The full text of {{ indexedBooks }} book{{ indexedBooks === 1 ? '' : 's' }} — find the passage you
                    half remember, and the book it is in.
                </template>
            </p>

            <div class="mt-6">
                <label class="sr-only" for="q">Search inside the books</label>
                <input
                    id="q"
                    v-model="term"
                    type="search"
                    autofocus
                    placeholder="a line you remember, a name, a place…"
                    class="border-line bg-raised text-content placeholder:text-muted focus:border-marigold w-full rounded-[--radius-ui] text-base focus:ring-0"
                />

                <p v-if="book" class="text-muted mt-2 text-sm">
                    <Link href="/search-inside" class="underline hover:no-underline">Search every book instead</Link>
                </p>
            </div>

            <p v-if="hasSearched" class="text-muted mt-6 text-sm" aria-live="polite">
                <template v-if="searching">Searching…</template>
                <template v-else-if="total === 0">No passages match that.</template>
                <template v-else>
                    {{ total }} passage{{ total === 1 ? '' : 's' }}
                    <template v-if="total > results.length">, showing the first {{ results.length }}</template>
                </template>
            </p>

            <!-- Results -->
            <ul v-if="results.length" role="list" class="divide-line mt-4 divide-y">
                <li v-for="hit in results" :key="hit.id" class="py-6">
                    <div class="flex gap-4">
                        <Link :href="`/books/${hit.bookSlug}`" class="shrink-0">
                            <BookCover
                                :src="hit.cover"
                                :title="hit.bookTitle"
                                :author="hit.author"
                                class="cover-shadow aspect-2/3 w-14 rounded-[--radius-cover]"
                            />
                        </Link>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <Link :href="`/books/${hit.bookSlug}`" class="font-semibold hover:underline">
                                    {{ hit.bookTitle }}
                                </Link>
                                <span class="text-muted text-sm">{{ hit.author }}</span>
                            </div>

                            <p v-if="hit.heading" class="text-muted mt-0.5 text-xs tracking-wide uppercase">
                                {{ hit.heading }}
                            </p>

                            <!-- eslint-disable-next-line vue/no-v-html -->
                            <p
                                class="font-reading text-content mt-3 text-sm/relaxed"
                                v-html="highlighted(hit.excerpt)"
                            />

                            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                <UiButton :href="`/books/${hit.bookSlug}`" size="sm" variant="secondary">
                                    {{ hit.pricePaise === 0 ? 'Read free' : formatPrice(hit.pricePaise) }}
                                </UiButton>
                                <Link
                                    :href="`/search-inside?q=${encodeURIComponent(query)}&book=${hit.bookSlug}`"
                                    class="text-muted hover:text-content underline hover:no-underline"
                                >
                                    Only this book
                                </Link>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>

            <EmptyState
                v-else-if="hasSearched && !searching"
                class="mt-8"
                title="Nothing matches that"
                body="Try fewer words, or a phrase you are sure of. Quotation marks look for the exact wording."
            />

            <div v-else-if="!hasSearched" class="border-line bg-raised mt-8 rounded-[--radius-ui] border p-5">
                <p class="text-muted text-sm">
                    Try
                    <Link href="/search-inside?q=universally+acknowledged" class="text-accent-text hover:underline">
                        universally acknowledged
                    </Link>
                    , or
                    <Link href="/search-inside?q=%22the+game+is+afoot%22" class="text-accent-text hover:underline">
                        “the game is afoot”
                    </Link>
                    — quotation marks look for the exact phrase.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
:deep(mark) {
    background: color-mix(in oklch, var(--color-marigold) 42%, transparent);
    color: inherit;
    border-radius: 2px;
    padding: 0 0.1em;
}
</style>
