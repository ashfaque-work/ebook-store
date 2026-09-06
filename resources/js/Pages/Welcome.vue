<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BookCard from '@/Components/Ui/BookCard.vue';
import BookCover from '@/Components/BookCover.vue';
import Shelf from '@/Components/Ui/Shelf.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { formatPrice } from '@/lib/money';

const props = defineProps({
    mode: { type: String, default: 'shelves' },
    genres: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // shelves mode
    featured: { type: Object, default: null },
    newest: { type: Array, default: () => [] },
    shelves: { type: Array, default: null },
    continueReading: { type: Object, default: null },
    // results mode
    books: { type: Object, default: null },
});

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
              The hero opens a book rather than selling one. A bookshop's most
 characteristic moment is reading the first lines, so that is what
 leads — set at true reading size, in the reading face.
            -->
            <section v-if="featured" class="border-line border-b">
                <div
                    class="mx-auto grid max-w-[1200px] gap-8 px-4 py-12 sm:px-6 md:grid-cols-[minmax(0,15rem)_1fr] md:gap-12 md:py-20"
                >
                    <Link :href="`/books/${featured.slug}`" class="mx-auto w-40 md:mx-0 md:w-full">
                        <BookCover
                            :src="featured.cover_image_path"
                            :title="featured.title"
                            :author="featured.author"
                            class="cover-shadow aspect-2/3 w-full rounded-[--radius-cover]"
                        />
                    </Link>

                    <div class="flex flex-col justify-center">
                        <blockquote class="font-reading text-content measure text-lg/relaxed md:text-xl/relaxed">
                            <p>{{ featured.excerpt }}</p>
                        </blockquote>

                        <p class="mt-6 text-2xl font-semibold tracking-tight">{{ featured.title }}</p>
                        <p class="text-muted mt-1 text-sm">
                            {{ featured.author }}
                            <span v-if="!featured.isExcerpt">&middot; from the description</span>
                        </p>

                        <div class="mt-7 flex flex-wrap items-center gap-4">
                            <UiButton v-if="featured.hasSample" :href="`/read/${featured.slug}/sample`" size="lg">
                                Read the first chapter
                            </UiButton>
                            <UiButton v-else :href="`/books/${featured.slug}`" size="lg">Look inside</UiButton>
                            <span class="text-muted text-sm">
                                {{ formatPrice(featured.price_paise) }} &middot; read in your browser or download
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <div class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6">
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
