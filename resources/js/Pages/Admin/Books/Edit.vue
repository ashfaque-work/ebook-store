<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import BookCover from '@/Components/BookCover.vue';
import { paiseToRupees } from '@/lib/money';

const props = defineProps({
    book: Object,
    authors: Array,
    genres: Array,
    hasBeenPurchased: Boolean,
    hasSample: Boolean,
});

const form = useForm({
    _method: 'PUT', // Important for file uploads with PUT requests
    title: props.book.title,
    author_id: props.book.author_id,
    genre_id: props.book.genre_id,
    description: props.book.description,
    price: paiseToRupees(props.book.price_paise),
    is_published: Boolean(props.book.is_published),
    cover_image: null,
    book_file: null,
    sample_file: null,
});

const submit = () => {
    form.post(`/admin/books/${props.book.id}`, {
        forceFormData: true,
    });
};

</script>

<template>

    <Head title="Edit Book" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Book</h2>
        </template>

        <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 shadow-xs sm:rounded-lg p-6">
            <form @submit.prevent="submit">
                <!-- Current Cover Image -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Cover</label>
                    <BookCover :src="book.cover_image_path" :title="book.title"
                        class="mt-1 h-32 w-24 rounded-md" />
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                    <input type="text" v-model="form.title" id="title"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"
                        required />
                    <div v-if="form.errors.title" class="text-sm text-red-600 mt-2">{{ form.errors.title }}</div>
                </div>

                <!-- Author -->
                <div class="mt-4">
                    <label for="author_id"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Author</label>
                    <select v-model="form.author_id" id="author_id"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"
                        required>
                        <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                    </select>
                    <div v-if="form.errors.author_id" class="text-sm text-red-600 mt-2">{{ form.errors.author_id }}
                    </div>
                </div>

                <!-- Genre -->
                <div class="mt-4">
                    <label for="genre_id"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Genre</label>
                    <select v-model="form.genre_id" id="genre_id"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"
                        required>
                        <option v-for="genre in genres" :key="genre.id" :value="genre.id">{{ genre.name }}</option>
                    </select>
                    <div v-if="form.errors.genre_id" class="text-sm text-red-600 mt-2">{{ form.errors.genre_id }}</div>
                </div>

                <!-- Description -->
                <div class="mt-4">
                    <label for="description"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea v-model="form.description" id="description" rows="4"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"></textarea>
                    <div v-if="form.errors.description" class="text-sm text-red-600 mt-2">{{ form.errors.description }}
                    </div>
                </div>

                <!-- Price -->
                <div class="mt-4">
                    <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                    <input type="number" step="0.01" v-model="form.price" id="price"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"
                        required />
                    <div v-if="form.errors.price" class="text-sm text-red-600 mt-2">{{ form.errors.price }}</div>
                </div>

                <!-- Cover Image -->
                <div class="mt-4">
                    <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New
                        Cover Image
                        (Optional)</label>
                    <input type="file" @input="form.cover_image = $event.target.files[0]" id="cover_image"
                        class="mt-1 block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-hidden dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" />
                    <div v-if="form.errors.cover_image" class="text-sm text-red-600 mt-2">{{ form.errors.cover_image }}
                    </div>
                </div>

                <!-- Book File -->
                <div class="mt-4">
                    <label for="book_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Book
                        File
                        (Optional)</label>
                    <input type="file" @input="form.book_file = $event.target.files[0]" id="book_file"
                        class="mt-1 block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-hidden dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" />
                    <div v-if="form.errors.book_file" class="text-sm text-red-600 mt-2">{{ form.errors.book_file }}
                    </div>
                </div>

                <!-- Free sample -->
                <div class="mt-4">
                    <label for="sample_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Free sample (PDF/EPUB)
                    </label>
                    <input type="file" @input="form.sample_file = $event.target.files[0]" id="sample_file"
                        class="mt-1 block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-hidden dark:bg-gray-700 dark:border-gray-600" />
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        <span v-if="hasSample">A sample is already uploaded; choosing a file replaces it.</span>
                        <span v-else>Usually the first chapter. Readable without an account.</span>
                    </p>
                    <div v-if="form.errors.sample_file" class="text-sm text-red-600 mt-2">{{ form.errors.sample_file }}</div>
                </div>

                <!-- Published -->
                <div class="mt-4 flex items-start gap-3">
                    <input type="checkbox" v-model="form.is_published" id="is_published"
                        class="mt-1 rounded-sm border-gray-300 text-blue-600 shadow-xs focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900" />
                    <label for="is_published" class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium">Published</span>
                        <span class="block text-gray-500 dark:text-gray-400">Unpublished books stay in the admin panel but
                            never appear in the catalogue. This is how a book that has already been sold is retired.</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <Link href="/admin/books" class="text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">Update Book</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
