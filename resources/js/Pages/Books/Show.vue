<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatPaise, formatPrice } from '@/lib/money';
import BookCover from '@/Components/BookCover.vue';

const props = defineProps({
    book: Object,
    isBookInCart: Boolean,
    isPurchased: Boolean,
    hasSample: Boolean,
});

const addToCart = () => {
    router.post(`/cart/${props.book.id}`);
};
</script>

<template>

    <Head :title="book.title" />

    <GuestLayout>
        <div class="max-w-4xl mx-auto bg-white dark:bg-gray-800 shadow-xs sm:rounded-lg p-6 md:p-8">
            <div class="md:flex">
                <div class="md:w-1/3">
                    <BookCover :src="book.cover_image_path" :title="book.title" :author="book.author?.name"
                        class="w-full aspect-2/3 rounded-lg shadow-lg" />
                </div>
                <div class="md:w-2/3 md:pl-8 mt-6 md:mt-0 flex flex-col">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ book.title }}</h1>
                    <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                        by {{ book.author.name }} in {{ book.genre.name }}
                    </p>
                    <p class="mt-4 text-gray-700 dark:text-gray-300 grow">{{ book.description }}</p>

                    <div class="mt-6 flex items-center justify-between">
                        <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ formatPrice(book.price_paise) }}</span>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Owned: reading comes first, the file second. -->
                            <template v-if="isPurchased">
                                <Link :href="route('reader.show', book.slug)">
                                <PrimaryButton>Read</PrimaryButton>
                                </Link>
                                <a :href="route('library.download', book.id)"
                                    class="text-sm text-gray-600 underline hover:no-underline dark:text-gray-400">
                                    Download
                                </a>
                            </template>

                            <template v-else>
                                <PrimaryButton v-if="!isBookInCart" @click="addToCart">Add to Cart</PrimaryButton>
                                <Link v-else href="/cart">
                                <SecondaryButton>Go to Cart</SecondaryButton>
                                </Link>

                                <!-- The strongest thing this page can do is let
                                     someone start reading. -->
                                <Link v-if="hasSample" :href="route('reader.sample', book.slug)"
                                    class="text-sm font-medium text-blue-600 underline hover:no-underline dark:text-blue-400">
                                Read the first chapter
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>