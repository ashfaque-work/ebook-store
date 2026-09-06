<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    genres: Object, // Laravel paginator: { data, links, ... }
});

const deleteGenre = (id) => {
    if (confirm('Are you sure you want to delete this genre?')) {
        router.delete(`/admin/genres/${id}`);
    }
};
</script>

<template>

    <Head title="Genres Management" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Genres Management</h2>
        </template>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xs sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-end mb-4">
                    <Link href="/admin/genres/create">
                    <PrimaryButton>Add Genre</PrimaryButton>
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Name</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Slug</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="genre in genres.data" :key="genre.id">
                                <td class="px-6 py-4 whitespace-nowrap">{{ genre.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ genre.slug }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link :href="`/admin/genres/${genre.id}/edit`"
                                        class="text-blue-600 dark:text-blue-400 hover:underline mr-4">Edit</Link>
                                    <button @click="deleteGenre(genre.id)"
                                        class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="genres.data.length === 0">
                                <td colspan="3" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">
                                    No genres found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="genres.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
