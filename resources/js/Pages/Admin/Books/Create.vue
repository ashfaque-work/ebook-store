<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    authors: Array,
    genres: Array,
});

const form = useForm({
    title: '',
    author_id: '',
    genre_id: '',
    description: '',
    price: '',
    cover_image: null,
    book_file: null,
});

const submit = () => {
    form.post('/admin/books', {
        forceFormData: true,
    });
};
</script>

<template>

    <Head title="Add New Book" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Add New Book</h2>
        </template>

        <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
            <form @submit.prevent="submit">
                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                    <input type="text" v-model="form.title" id="title"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                        required />
                    <div v-if="form.errors.title" class="text-sm text-red-600 mt-2">{{ form.errors.title }}</div>
                </div>

                <!-- Author -->
                <div class="mt-4">
                    <label for="author_id"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Author</label>
                    <select v-model="form.author_id" id="author_id"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                        required>
                        <option value="" disabled>Select an author</option>
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
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                        required>
                        <option value="" disabled>Select a genre</option>
                        <option v-for="genre in genres" :key="genre.id" :value="genre.id">{{ genre.name }}</option>
                    </select>
                    <div v-if="form.errors.genre_id" class="text-sm text-red-600 mt-2">{{ form.errors.genre_id }}</div>
                </div>

                <!-- Description -->
                <div class="mt-4">
                    <label for="description"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea v-model="form.description" id="description" rows="4"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"></textarea>
                    <div v-if="form.errors.description" class="text-sm text-red-600 mt-2">{{ form.errors.description }}
                    </div>
                </div>

                <!-- Price -->
                <div class="mt-4">
                    <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                    <input type="number" step="0.01" v-model="form.price" id="price"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
                        required />
                    <div v-if="form.errors.price" class="text-sm text-red-600 mt-2">{{ form.errors.price }}</div>
                </div>

                <!-- Cover Image -->
                <div class="mt-4">
                    <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cover
                        Image</label>
                    <input type="file" @input="form.cover_image = $event.target.files[0]" id="cover_image"
                        class="mt-1 block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" />
                    <div v-if="form.errors.cover_image" class="text-sm text-red-600 mt-2">{{ form.errors.cover_image }}
                    </div>
                </div>

                <!-- Book File -->
                <div class="mt-4">
                    <label for="book_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Book File
                        (PDF/EPUB)</label>
                    <input type="file" @input="form.book_file = $event.target.files[0]" id="book_file"
                        class="mt-1 block w-full text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" />
                    <div v-if="form.errors.book_file" class="text-sm text-red-600 mt-2">{{ form.errors.book_file }}
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <Link href="/admin/books" class="text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">Save Book</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
