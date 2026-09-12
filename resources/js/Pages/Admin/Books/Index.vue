<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatPaise, formatPrice } from '@/lib/money';
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

    if (confirm(`Delete"${book.title}"? This cannot be undone.`)) {
        router.delete(`/admin/books/${book.id}`);
    }
};
</script>

<template>
    <Head title="Books Management" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Books Management</h2>
        </template>

        <div class="bg-raised overflow-hidden shadow-xs sm:rounded-[--radius-ui]">
            <div class="text-content p-6">
                <div class="mb-4 flex justify-end">
                    <Link href="/admin/books/create">
                        <PrimaryButton>Add Book</PrimaryButton>
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="divide-line min-w-full divide-y">
                        <thead class="bg-surface">
                            <tr>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
                                >
                                    Cover
                                </th>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
                                >
                                    Title
                                </th>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
                                >
                                    Author
                                </th>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
                                >
                                    Genre
                                </th>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
                                >
                                    Price
                                </th>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-right text-xs font-medium tracking-wider uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-raised divide-line divide-y">
                            <tr v-for="book in books.data" :key="book.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <BookCover
                                        :src="book.cover_image_path"
                                        :title="book.title"
                                        class="h-16 w-12 rounded-[--radius-ui]"
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ book.title }}
                                    <span
                                        v-if="!book.is_published"
                                        class="bg-line text-content ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
                                    >
                                        Draft
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ book.author.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ book.genre.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ formatPrice(book.price_paise) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <Link
                                        :href="`/admin/books/${book.id}/edit`"
                                        class="text-accent-text mr-4 hover:underline"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="deleteBook(book)"
                                        class="text-red-600 hover:underline dark:text-red-400"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="books.data.length === 0">
                                <td colspan="6" class="text-muted px-6 py-4 text-center whitespace-nowrap">
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
