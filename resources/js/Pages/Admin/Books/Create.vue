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
    excerpt: '',
    description: '',
    price: '',
    is_published: true,
    cover_image: null,
    book_file: null,
    sample_file: null,
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
            <h2 class="text-content text-xl leading-tight font-semibold">Add New Book</h2>
        </template>

        <div class="bg-raised mx-auto max-w-2xl p-6 shadow-xs sm:rounded-[--radius-ui]">
            <form @submit.prevent="submit">
                <!-- Title -->
                <div>
                    <label for="title" class="text-content block text-sm font-medium">Title</label>
                    <input
                        type="text"
                        v-model="form.title"
                        id="title"
                        class="border-line bg-surface text-content focus:border-marigold focus:ring-marigold mt-1 block w-full rounded-[--radius-ui] shadow-xs"
                        required
                    />
                    <div v-if="form.errors.title" class="mt-2 text-sm text-red-600">{{ form.errors.title }}</div>
                </div>

                <!-- Author -->
                <div class="mt-4">
                    <label for="author_id" class="text-content block text-sm font-medium">Author</label>
                    <select
                        v-model="form.author_id"
                        id="author_id"
                        class="border-line bg-surface text-content focus:border-marigold focus:ring-marigold mt-1 block w-full rounded-[--radius-ui] shadow-xs"
                        required
                    >
                        <option value="" disabled>Select an author</option>
                        <option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option>
                    </select>
                    <div v-if="form.errors.author_id" class="mt-2 text-sm text-red-600">
                        {{ form.errors.author_id }}
                    </div>
                </div>

                <!-- Genre -->
                <div class="mt-4">
                    <label for="genre_id" class="text-content block text-sm font-medium">Genre</label>
                    <select
                        v-model="form.genre_id"
                        id="genre_id"
                        class="border-line bg-surface text-content focus:border-marigold focus:ring-marigold mt-1 block w-full rounded-[--radius-ui] shadow-xs"
                        required
                    >
                        <option value="" disabled>Select a genre</option>
                        <option v-for="genre in genres" :key="genre.id" :value="genre.id">{{ genre.name }}</option>
                    </select>
                    <div v-if="form.errors.genre_id" class="mt-2 text-sm text-red-600">{{ form.errors.genre_id }}</div>
                </div>

                <!-- Description -->
                <div class="mt-4">
                    <label for="description" class="text-content block text-sm font-medium">Description</label>
                    <textarea
                        v-model="form.description"
                        id="description"
                        rows="4"
                        class="border-line bg-surface text-content focus:border-marigold focus:ring-marigold mt-1 block w-full rounded-[--radius-ui] shadow-xs"
                    ></textarea>
                    <div v-if="form.errors.description" class="mt-2 text-sm text-red-600">
                        {{ form.errors.description }}
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="mt-4">
                    <label for="excerpt" class="text-content block text-sm font-medium">Opening passage</label>
                    <textarea
                        v-model="form.excerpt"
                        id="excerpt"
                        rows="4"
                        class="bg-surface text-content mt-1 block w-full rounded-[--radius-ui] border-gray-300 shadow-xs"
                    ></textarea>
                    <p class="text-muted mt-1 text-sm">
                        The real first lines, not a blurb. The home page leads with this.
                    </p>
                    <div v-if="form.errors.excerpt" class="mt-2 text-sm text-red-600">{{ form.errors.excerpt }}</div>
                </div>

                <!-- Price -->
                <div class="mt-4">
                    <label for="price" class="text-content block text-sm font-medium">Price</label>
                    <input
                        type="number"
                        step="0.01"
                        v-model="form.price"
                        id="price"
                        class="border-line bg-surface text-content focus:border-marigold focus:ring-marigold mt-1 block w-full rounded-[--radius-ui] shadow-xs"
                        required
                    />
                    <div v-if="form.errors.price" class="mt-2 text-sm text-red-600">{{ form.errors.price }}</div>
                </div>

                <!-- Cover Image -->
                <div class="mt-4">
                    <label for="cover_image" class="text-content block text-sm font-medium">Cover Image</label>
                    <input
                        type="file"
                        @input="form.cover_image = $event.target.files[0]"
                        id="cover_image"
                        class="mt-1 block w-full cursor-pointer rounded-[--radius-ui] border border-gray-300 bg-gray-50 text-gray-900 focus:outline-hidden dark:placeholder-gray-400"
                    />
                    <div v-if="form.errors.cover_image" class="mt-2 text-sm text-red-600">
                        {{ form.errors.cover_image }}
                    </div>
                </div>

                <!-- Book File -->
                <div class="mt-4">
                    <label for="book_file" class="text-content block text-sm font-medium">Book File (PDF/EPUB)</label>
                    <input
                        type="file"
                        @input="form.book_file = $event.target.files[0]"
                        id="book_file"
                        class="mt-1 block w-full cursor-pointer rounded-[--radius-ui] border border-gray-300 bg-gray-50 text-gray-900 focus:outline-hidden dark:placeholder-gray-400"
                    />
                    <div v-if="form.errors.book_file" class="mt-2 text-sm text-red-600">
                        {{ form.errors.book_file }}
                    </div>
                </div>

                <!-- Free sample -->
                <div class="mt-4">
                    <label for="sample_file" class="text-content block text-sm font-medium">
                        Free sample (PDF/EPUB)
                    </label>
                    <input
                        type="file"
                        @input="form.sample_file = $event.target.files[0]"
                        id="sample_file"
                        class="mt-1 block w-full cursor-pointer rounded-[--radius-ui] border border-gray-300 bg-gray-50 text-gray-900 focus:outline-hidden"
                    />
                    <p class="text-muted mt-1 text-sm">
                        Usually the first chapter. Readable without an account, and the single biggest thing that turns
                        a browser into a buyer.
                    </p>
                    <div v-if="form.errors.sample_file" class="mt-2 text-sm text-red-600">
                        {{ form.errors.sample_file }}
                    </div>
                </div>

                <!-- Published -->
                <div class="mt-4 flex items-start gap-3">
                    <input
                        type="checkbox"
                        v-model="form.is_published"
                        id="is_published"
                        class="focus:ring-marigold mt-1 rounded-sm border-gray-300 text-blue-600 shadow-xs"
                    />
                    <label for="is_published" class="text-content text-sm">
                        <span class="font-medium">Published</span>
                        <span class="text-muted block">
                            Unpublished books stay in the admin panel but never appear in the catalogue. This is how a
                            book that has already been sold is retired.
                        </span>
                    </label>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <Link href="/admin/books" class="text-muted mr-4 hover:underline">Cancel</Link>
                    <PrimaryButton :disabled="form.processing">Save Book</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
