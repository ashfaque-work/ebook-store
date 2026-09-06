<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { formatInr, formatPrice } from '@/lib/money';
import BookCover from '@/Components/BookCover.vue';

const props = defineProps({
    books: Object, // Laravel paginator: { data, links, ... }
    genres: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const genre = ref(props.filters?.genre ?? '');

const applyFilters = () => {
    router.get('/', {
        search: search.value || undefined,
        genre: genre.value || undefined,
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

// Small debounce so the free-text search doesn't fire a request per keystroke.
let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});
watch(genre, applyFilters);
</script>

<template>
    <Head title="Browse eBooks" />

    <GuestLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Browse eBooks</h2>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <input v-model="search" type="search" placeholder="Search title or author…"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        <select v-model="genre"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All genres</option>
                            <option v-for="g in genres" :key="g.id" :value="g.slug">{{ g.name }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="books.data.length > 0"
                    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <Link v-for="book in books.data" :key="book.id" :href="`/books/${book.slug}`" class="group">
                    <div
                        class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden transform hover:scale-105 transition-transform duration-300">
                        <BookCover :src="book.cover_image_path" :title="book.title" :author="book.author?.name"
                            class="w-full h-64" />
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ book.title }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">by {{ book.author.name }}</p>
                            <p class="mt-2 font-semibold text-blue-600 dark:text-blue-400">{{ formatPrice(book.price) }}</p>
                        </div>
                    </div>
                    </Link>
                </div>
                <div v-else>
                    <p class="text-gray-500 dark:text-gray-400">No books match your search. Try a different term or
                        genre.</p>
                </div>

                <Pagination :links="books.links" />
            </div>
        </div>
    </GuestLayout>
</template>
