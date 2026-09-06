<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatInr, formatPrice } from '@/lib/money';
import BookCover from '@/Components/BookCover.vue';

defineProps({
    books: Object, // Laravel paginator: { data, links, ... }
});

const deleteBook = (book) => {
    // The server refuses this too; warning here saves a pointless round trip.
    if (book.order_items_count > 0) {
        alert(`"${book.title}" has been purchased and cannot be deleted. Unpublish it instead.`);
        return;
    }

    if (confirm(`Delete "${book.title}"? This cannot be undone.`)) {
        router.delete(`/admin/books/${book.id}`);
    }
};

</script>

<template>

    <Head title="Books Management" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Books Management</h2>
        </template>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-end mb-4">
                    <Link href="/admin/books/create">
                    <PrimaryButton>Add Book</PrimaryButton>
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Cover</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Title</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Author</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Genre</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Price</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="book in books.data" :key="book.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <BookCover :src="book.cover_image_path" :title="book.title" class="h-16 w-12 rounded-md" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ book.title }}
                                    <span v-if="!book.is_published"
                                        class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">Draft</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ book.author.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ book.genre.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ formatPrice(book.price) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link :href="`/admin/books/${book.id}/edit`"
                                        class="text-blue-600 dark:text-blue-400 hover:underline mr-4">Edit</Link>
                                    <button @click="deleteBook(book)"
                                        class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="books.data.length === 0">
                                <td colspan="6" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">
                                    No books found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="books.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
