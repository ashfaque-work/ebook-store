<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { ref, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatPaise, formatPrice } from '@/lib/money';
import BookCover from '@/Components/BookCover.vue';

const props = defineProps({
    books: Object, // Laravel paginator: { data, links, ... }
    filters: { type: Object, default: () => ({}) },
    genres: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const genre = ref(props.filters?.genre ?? '');

const apply = () => {
    router.get(
        '/admin/books',
        {
            search: search.value || undefined,
            status: status.value || undefined,
            genre: genre.value || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

// Debounced so a search does not fire a request per keystroke.
let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch([status, genre], apply);

const clearFilters = () => {
    search.value = '';
    status.value = '';
    genre.value = '';
};

// Deleting is the one irreversible thing on this page, so it asks in the
// store's own dialog rather than a browser confirm() — which looks like a
// phishing prompt and cannot say why an option is unavailable.
const pendingDelete = ref(null);

const askToDelete = (book) => (pendingDelete.value = book);

const confirmDelete = () => {
    const book = pendingDelete.value;
    pendingDelete.value = null;

    if (book) router.delete(`/admin/books/${book.id}`, { preserveScroll: true });
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
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search title or author"
                            aria-label="Search books"
                            class="border-line bg-surface text-content placeholder:text-muted focus:border-marigold w-full rounded-[--radius-ui] text-sm focus:ring-0 sm:w-64"
                        />

                        <select
                            v-model="status"
                            aria-label="Filter by status"
                            class="border-line bg-surface text-content focus:border-marigold rounded-[--radius-ui] text-sm focus:ring-0"
                        >
                            <option value="">Any status</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="free">Free</option>
                            <option value="paid">Paid</option>
                        </select>

                        <select
                            v-model="genre"
                            aria-label="Filter by genre"
                            class="border-line bg-surface text-content focus:border-marigold rounded-[--radius-ui] text-sm focus:ring-0"
                        >
                            <option value="">Any genre</option>
                            <option v-for="g in genres" :key="g.id" :value="g.slug">{{ g.name }}</option>
                        </select>

                        <button
                            v-if="search || status || genre"
                            type="button"
                            class="text-muted hover:text-content text-sm underline hover:no-underline"
                            @click="clearFilters"
                        >
                            Clear
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <p class="text-muted shrink-0 text-sm">
                            {{ books.total }} book{{ books.total === 1 ? '' : 's' }}
                        </p>
                        <Link href="/admin/books/create">
                            <PrimaryButton>Add Book</PrimaryButton>
                        </Link>
                    </div>
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
                                        v-if="book.order_items_count === 0"
                                        type="button"
                                        @click="askToDelete(book)"
                                        class="text-red-600 hover:underline dark:text-red-400"
                                    >
                                        Delete
                                    </button>
                                    <span
                                        v-else
                                        class="text-muted cursor-help"
                                        :title="`Sold ${book.order_items_count} time(s). Unpublish it instead — deleting would take it out of someone's library.`"
                                    >
                                        Sold
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="books.data.length === 0">
                                <td colspan="6" class="text-muted px-6 py-8 text-center">
                                    Nothing matches those filters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="books.links" />
            </div>
        </div>
        <ConfirmDialog
            :open="pendingDelete !== null"
            @update:open="(value) => !value && (pendingDelete = null)"
            title="Delete this book?"
            :body="
                pendingDelete ? `“${pendingDelete.title}” and its files will be removed. This cannot be undone.` : ''
            "
            confirm-label="Delete book"
            destructive
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
