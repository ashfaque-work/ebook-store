<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BookCard from '@/Components/Ui/BookCard.vue';
import BookCover from '@/Components/BookCover.vue';
import Shelf from '@/Components/Ui/Shelf.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatPrice } from '@/lib/money';

const props = defineProps({
    book: Object,
    isBookInCart: Boolean,
    isPurchased: Boolean,
    hasSample: Boolean,
    related: { type: Array, default: null },
});

const adding = ref(false);
const claiming = ref(false);

const isFree = computed(() => props.book.price_paise === 0);

// One click, not a cart and a checkout with nothing to charge. Guests go via
// the sign-in page and land back here, because the book still has to be
// attached to an account for the library and the reader to work.
const claimFree = () => {
    claiming.value = true;
    router.post(route('books.claim', props.book.id), {}, { onFinish: () => (claiming.value = false) });
};

const addToCart = () => {
    adding.value = true;
    router.post(
        `/cart/${props.book.id}`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (adding.value = false),
        },
    );
};

const facts = computed(() =>
    [
        { label: 'Format', value: props.book.format },
        props.book.page_count ? { label: 'Length', value: `${props.book.page_count} pages` } : null,
        { label: 'Language', value: props.book.language === 'en' ? 'English' : props.book.language },
        props.book.isbn ? { label: 'ISBN', value: props.book.isbn } : null,
    ].filter(Boolean),
);
</script>

<template>
    <Head :title="book.title" />

    <GuestLayout>
        <div class="mx-auto max-w-[1200px] px-4 py-8 sm:px-6 lg:py-12">
            <nav aria-label="Breadcrumb" class="text-muted mb-6 text-sm">
                <Link href="/" class="hover:text-content">Books</Link>
                <span aria-hidden="true" class="px-2">/</span>
                <Link :href="`/?genre=${book.genre.slug}`" class="hover:text-content">{{ book.genre.name }}</Link>
            </nav>

            <div class="grid gap-10 lg:grid-cols-[minmax(0,18rem)_1fr] lg:gap-14">
                <!-- Cover + buy panel. Sticky on wide screens, a bottom bar on
 a phone, so the price is never scrolled away from. -->
                <div class="lg:sticky lg:top-24 lg:self-start">
                    <BookCover
                        :src="book.cover_image_path"
                        :title="book.title"
                        :author="book.author.name"
                        class="cover-shadow mx-auto aspect-2/3 w-48 rounded-[--radius-cover] lg:w-full"
                    />

                    <div class="mt-6 hidden lg:block">
                        <p v-if="!isPurchased" class="tabular text-2xl font-semibold">
                            {{ formatPrice(book.price_paise) }}
                        </p>

                        <div class="mt-4 grid gap-3">
                            <template v-if="isPurchased">
                                <UiButton :href="`/read/${book.slug}`" size="lg" block>Read</UiButton>
                                <UiButton :href="route('library.download', book.id)" external variant="secondary" block>
                                    Download
                                </UiButton>
                            </template>
                            <template v-else-if="isFree">
                                <UiButton size="lg" block :disabled="claiming" @click="claimFree">
                                    {{ claiming ? 'Opening…' : 'Read free' }}
                                </UiButton>
                            </template>
                            <template v-else>
                                <UiButton v-if="!isBookInCart" size="lg" block :disabled="adding" @click="addToCart">
                                    {{ adding ? 'Adding…' : 'Add to cart' }}
                                </UiButton>
                                <UiButton v-else href="/cart" variant="secondary" size="lg" block>Go to cart</UiButton>
                                <UiButton v-if="hasSample" :href="`/read/${book.slug}/sample`" variant="ghost" block>
                                    Read the first chapter
                                </UiButton>
                            </template>
                        </div>

                        <dl class="border-line mt-8 space-y-2 border-t pt-6 text-sm">
                            <div v-for="fact in facts" :key="fact.label" class="flex justify-between gap-4">
                                <dt class="text-muted">{{ fact.label }}</dt>
                                <dd class="text-content">{{ fact.value }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="min-w-0">
                    <h1 class="text-display font-semibold tracking-tight">{{ book.title }}</h1>
                    <p class="text-muted mt-2 text-lg">{{ book.author.name }}</p>

                    <p v-if="isPurchased" class="text-verdigris mt-4 inline-flex items-center gap-2 text-sm">
                        In your library
                    </p>

                    <section class="mt-8">
                        <h2 class="text-lg">About this book</h2>
                        <p class="font-reading text-content measure mt-3 text-base/relaxed">{{ book.description }}</p>
                    </section>

                    <!-- The excerpt sits on the page, not behind a modal. The
 strongest argument for a book is the book. -->
                    <section v-if="book.excerpt" class="mt-10">
                        <h2 class="text-lg">From the first chapter</h2>
                        <blockquote
                            class="border-marigold font-reading text-content measure mt-3 border-s-2 ps-5 text-base/loose"
                        >
                            <p>{{ book.excerpt }}</p>
                        </blockquote>
                        <UiButton
                            v-if="hasSample && !isPurchased"
                            :href="`/read/${book.slug}/sample`"
                            variant="secondary"
                            class="mt-5"
                        >
                            Keep reading — free
                        </UiButton>
                    </section>

                    <dl class="border-line mt-10 grid grid-cols-2 gap-4 border-t pt-6 text-sm lg:hidden">
                        <div v-for="fact in facts" :key="fact.label">
                            <dt class="text-muted">{{ fact.label }}</dt>
                            <dd class="text-content mt-0.5">{{ fact.value }}</dd>
                        </div>
                    </dl>

                    <Deferred data="related">
                        <template #fallback>
                            <div class="bg-line/60 mt-12 h-48 rounded" aria-hidden="true" />
                        </template>

                        <Shelf v-if="related?.length" heading="You might also like">
                            <BookCard v-for="item in related" :key="item.id" :book="item" />
                        </Shelf>
                    </Deferred>
                </div>
            </div>
        </div>

        <!-- Mobile buy bar -->
        <div
            class="border-line bg-surface/95 sticky bottom-0 z-20 flex items-center gap-3 border-t px-4 py-3 backdrop-blur lg:hidden"
        >
            <p v-if="!isPurchased" class="tabular shrink-0 text-lg font-semibold">
                {{ formatPrice(book.price_paise) }}
            </p>

            <template v-if="isPurchased">
                <UiButton :href="`/read/${book.slug}`" block>Read</UiButton>
            </template>
            <template v-else-if="isFree">
                <UiButton block :disabled="claiming" @click="claimFree">
                    {{ claiming ? 'Opening…' : 'Read free' }}
                </UiButton>
            </template>
            <template v-else>
                <UiButton v-if="hasSample" :href="`/read/${book.slug}/sample`" variant="secondary" class="shrink-0">
                    Sample
                </UiButton>
                <UiButton v-if="!isBookInCart" block :disabled="adding" @click="addToCart">
                    {{ adding ? 'Adding…' : 'Add to cart' }}
                </UiButton>
                <UiButton v-else href="/cart" variant="secondary" block>Go to cart</UiButton>
            </template>
        </div>
    </GuestLayout>
</template>
