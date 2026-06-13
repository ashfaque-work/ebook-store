<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    books: Array,
});

const isEmpty = computed(() => props.books.length === 0);
</script>

<template>
    <Head title="My Library" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                My Library
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="isEmpty" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400 text-lg">You haven't purchased any books yet.</p>
                    <Link href="/" class="mt-4 inline-block text-blue-600 dark:text-blue-400 hover:underline">
                        Browse the store
                    </Link>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <div v-for="book in books" :key="book.id"
                        class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-sm flex flex-col">
                        <img :src="book.cover_image_path" :alt="book.title" class="w-full h-64 object-cover">
                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ book.title }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">by {{ book.author.name }}</p>
                            <a :href="route('library.download', book.id)"
                                class="mt-4 inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
