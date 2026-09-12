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
            <h2 class="text-content text-xl leading-tight font-semibold">Genres Management</h2>
        </template>

        <div class="bg-raised overflow-hidden shadow-xs sm:rounded-[--radius-ui]">
            <div class="text-content p-6">
                <div class="mb-4 flex justify-end">
                    <Link href="/admin/genres/create">
                        <PrimaryButton>Add Genre</PrimaryButton>
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
                                    Name
                                </th>
                                <th
                                    scope="col"
                                    class="text-muted px-6 py-3 text-left text-xs font-medium tracking-wider uppercase"
                                >
                                    Slug
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
                            <tr v-for="genre in genres.data" :key="genre.id">
                                <td class="px-6 py-4 whitespace-nowrap">{{ genre.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ genre.slug }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <Link
                                        :href="`/admin/genres/${genre.id}/edit`"
                                        class="text-accent-text mr-4 hover:underline"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="deleteGenre(genre.id)"
                                        class="text-red-600 hover:underline dark:text-red-400"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="genres.data.length === 0">
                                <td colspan="3" class="text-muted px-6 py-4 text-center whitespace-nowrap">
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
