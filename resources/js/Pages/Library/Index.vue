<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCover from '@/Components/BookCover.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    books: Object, // Laravel paginator: { data, links, ... }
    continueReading: { type: Object, default: null },
});

const isEmpty = computed(() => props.books.data.length === 0);
</script>

<template>
    <Head title="My Library" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                My Library
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="isEmpty" class="rounded-lg bg-white p-8 text-center shadow-xs dark:bg-gray-800">
                    <p class="text-lg text-gray-600 dark:text-gray-400">Nothing here yet.</p>
                    <Link href="/" class="mt-4 inline-block text-blue-600 hover:underline dark:text-blue-400">
                        Browse the shelves
                    </Link>
                </div>

                <template v-else>
                    <!-- The first thing a returning reader should see. -->
                    <section v-if="continueReading" class="mb-8">
                        <h3 class="mb-3 text-sm font-semibold text-gray-500 dark:text-gray-400">Continue reading</h3>

                        <Link :href="route('reader.show', continueReading.book.slug)"
                            class="flex gap-5 rounded-lg bg-white p-5 shadow-xs transition hover:shadow-md dark:bg-gray-800">
                        <BookCover :src="continueReading.book.cover_image_path" :title="continueReading.book.title"
                            :author="continueReading.book.author" class="h-32 w-22 shrink-0 rounded-sm" />

                        <div class="flex min-w-0 flex-1 flex-col justify-center">
                            <h4 class="truncate text-lg font-semibold text-gray-900 dark:text-white">
                                {{ continueReading.book.title }}
                            </h4>
                            <p class="truncate text-sm text-gray-600 dark:text-gray-400">
                                by {{ continueReading.book.author }}
                            </p>

                            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div class="h-full rounded-full bg-amber-500"
                                    :style="{ width: `${continueReading.percent}%` }" />
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ continueReading.percent }}% read &middot; pick up where you left off
                            </p>
                        </div>
                        </Link>
                    </section>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <div v-for="book in books.data" :key="book.id"
                            class="flex flex-col overflow-hidden rounded-lg bg-white shadow-xs dark:bg-gray-800">
                            <Link :href="route('reader.show', book.slug)">
                            <BookCover :src="book.cover_image_path" :title="book.title" :author="book.author?.name"
                                class="h-64 w-full" />
                            </Link>

                            <div class="flex flex-1 flex-col p-4">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ book.title }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">by {{ book.author.name }}</p>

                                <div class="mt-4 flex items-center gap-3">
                                    <Link :href="route('reader.show', book.slug)"
                                        class="inline-flex flex-1 items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:outline-solid focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500">
                                    Read
                                    </Link>
                                    <a :href="route('library.download', book.id)"
                                        class="text-sm text-gray-600 underline hover:no-underline dark:text-gray-400">
                                        Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Pagination :links="books.links" />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
