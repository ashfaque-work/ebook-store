<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BookCard from '@/Components/Ui/BookCard.vue';
import BookCover from '@/Components/BookCover.vue';
import Shelf from '@/Components/Ui/Shelf.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatPrice } from '@/lib/money';

const props = defineProps({
    mode: { type: String, default: 'shelves' },
    genres: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // shelves mode
    featured: { type: Object, default: null },
    newest: { type: Array, default: () => [] },
    shelves: { type: Array, default: null },
    stats: { type: Object, default: () => ({ books: 0, free: 0 }) },
    continueReading: { type: Object, default: null },
    // results mode
    books: { type: Object, default: null },
});

/*
 * The drop cap is for prose that opens on a word. An excerpt that opens on a
 * quotation mark would set the quote three lines tall — ::first-letter takes
 * leading punctuation with it — and that reads as a mistake rather than a
 * flourish.
 */
const opensOnALetter = computed(() => /^\p{L}/u.test(props.featured?.excerpt ?? ''));

const search = ref(props.filters?.search ?? '');
const genre = ref(props.filters?.genre ?? '');

const apply = () => {
    router.get(
        '/',
        {
            search: search.value || undefined,
            genre: genre.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch(genre, apply);

const clearFilters = () => {
    search.value = '';
    genre.value = '';
};
</script>

<template>
    <Head title="Books worth your evening" />

    <GuestLayout>
        <!-- ------------------------------------------------ browsing -->
        <template v-if="mode === 'shelves'">
            <!--
              The window of a bookshop, which is a book — open, lit, and set
              at the size it will be read at. Not a strapline above two
              buttons: the strongest argument for a book has always been its
              first paragraph, and this shop has fifty-four of them going
              spare.
            -->
            <section class="hero relative isolate overflow-hidden">
                <div class="hero-glow" aria-hidden="true" />

                <!--
                  The page needs a heading that says what the page is, and
                  this design deliberately has no strapline to carry one. So
                  it is here for a screen reader and a crawler, and the window
                  is left to make the argument visually.
                -->
                <h1 class="sr-only">
                    Books worth your evening — {{ stats.free }} classics, free to read in your browser
                </h1>

                <div
                    class="relative mx-auto grid max-w-[1060px] items-center gap-10 px-4 py-14 sm:px-6 md:min-h-[min(76vh,42rem)] md:grid-cols-[minmax(0,21rem)_1fr] md:gap-16 md:py-20"
                >
                    <Link
                        v-if="featured"
                        :href="`/books/${featured.slug}`"
                        class="group mx-auto block w-44 sm:w-56 md:w-full"
                        :aria-label="`${featured.title} by ${featured.author}`"
                    >
                        <BookCover
                            :src="featured.cover_image_path"
                            :title="featured.title"
                            :author="featured.author"
                            class="jacket-lit cover-lift aspect-2/3 w-full rounded-[--radius-cover]"
                        />
                    </Link>

                    <div v-if="featured" class="min-w-0">
                        <blockquote>
                            <p class="opening-lines text-content" :class="{ 'has-drop-cap': opensOnALetter }">
                                {{ featured.excerpt }}
                            </p>
                        </blockquote>

                        <h2 class="mt-8 text-2xl font-semibold tracking-tight md:text-3xl">
                            {{ featured.title }}
                        </h2>
                        <p class="text-muted font-reading mt-1 text-base">{{ featured.author }}</p>

                        <div class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-3">
                            <UiButton
                                :href="featured.hasSample ? `/read/${featured.slug}/sample` : `/books/${featured.slug}`"
                                size="lg"
                            >
                                {{ featured.hasSample ? 'Keep reading' : 'Look inside' }}
                            </UiButton>

                            <!--
                              The catalogue's size said once, in a sentence,
                              rather than as a row of big numbers with small
                              labels under them.
                            -->
                            <p class="text-muted font-reading text-sm">
                                <Link href="#shelves" class="hover:text-content underline underline-offset-4">
                                    {{ stats.free }} more
                                </Link>
                                on the shelves, free to open, nothing to install.
                            </p>
                        </div>
                    </div>

                    <!-- Nothing published yet: the shelves speak for themselves. -->
                    <div v-else class="md:col-span-2">
                        <p class="text-muted measure font-reading text-base/relaxed">Nothing on the shelves yet.</p>
                    </div>
                </div>
            </section>

            <div id="shelves" class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6">
                <!-- Where a returning reader wants to land. -->
                <Link
                    v-if="continueReading"
                    :href="`/read/${continueReading.slug}`"
                    class="border-line bg-raised hover:border-marigold mb-10 flex items-center gap-4 rounded-[--radius-ui] border px-5 py-4"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-muted text-xs">Continue reading</p>
                        <p class="truncate font-semibold">{{ continueReading.title }}</p>
                        <div class="bg-line mt-2 h-1 overflow-hidden rounded-full">
                            <div
                                class="bg-marigold h-full rounded-full"
                                :style="{ width: `${continueReading.percent}%` }"
                            />
                        </div>
                    </div>
                    <span class="tabular text-muted shrink-0 text-sm">{{ continueReading.percent }}%</span>
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by title or author"
                        aria-label="Search books"
                        class="border-line bg-raised text-content placeholder:text-muted focus:border-marigold w-full max-w-sm rounded-[--radius-ui] text-sm focus:ring-0"
                    />
                    <Link
                        v-for="g in genres.slice(0, 8)"
                        :key="g.id"
                        :href="`/?genre=${g.slug}`"
                        class="border-line text-muted hover:border-marigold hover:text-content rounded-full border px-3 py-1.5 text-sm"
                    >
                        {{ g.name }}
                    </Link>
                </div>

                <Shelf v-if="newest.length" heading="New this week" class="mt-10">
                    <BookCard v-for="book in newest" :key="book.id" :book="book" />
                </Shelf>

                <!-- Deferred: the rows below the fold do not need to hold up
 first paint on a slow connection. -->
                <Deferred data="shelves">
                    <template #fallback>
                        <div class="mt-12 space-y-3" aria-hidden="true">
                            <div class="bg-line h-5 w-40 rounded" />
                            <div class="bg-line/60 h-56 rounded" />
                        </div>
                    </template>

                    <Shelf
                        v-for="shelf in shelves"
                        :key="shelf.slug"
                        :heading="shelf.name"
                        :see-all-href="`/?genre=${shelf.slug}`"
                    >
                        <BookCard v-for="book in shelf.books" :key="book.id" :book="book" />
                    </Shelf>
                </Deferred>
            </div>
        </template>

        <!-- ------------------------------------------------- results -->
        <div v-else class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl">
                        <template v-if="filters.search">Results for &ldquo;{{ filters.search }}&rdquo;</template>
                        <template v-else>{{ genres.find((g) => g.slug === filters.genre)?.name ?? 'Books' }}</template>
                    </h1>
                    <p class="text-muted mt-1 text-sm">{{ books.total }} book{{ books.total === 1 ? '' : 's' }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by title or author"
                        aria-label="Search books"
                        class="border-line bg-raised text-content placeholder:text-muted focus:border-marigold rounded-[--radius-ui] text-sm focus:ring-0"
                    />
                    <select
                        v-model="genre"
                        aria-label="Filter by genre"
                        class="border-line bg-raised text-content focus:border-marigold rounded-[--radius-ui] text-sm focus:ring-0"
                    >
                        <option value="">All genres</option>
                        <option v-for="g in genres" :key="g.id" :value="g.slug">{{ g.name }}</option>
                    </select>
                </div>
            </div>

            <div
                v-if="books.data.length"
                class="mt-8 grid grid-cols-2 gap-x-5 gap-y-9 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
            >
                <BookCard v-for="book in books.data" :key="book.id" :book="book" />
            </div>

            <EmptyState
                v-else
                class="mt-8"
                title="Nothing matches that yet"
                body="Try a different spelling, or a broader search — the catalogue is still growing."
            >
                <UiButton variant="secondary" @click="clearFilters">Clear the filters</UiButton>
            </EmptyState>

            <Pagination v-if="books.data.length" :links="books.links" />
        </div>
    </GuestLayout>
</template>
