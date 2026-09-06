<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    authors: Object, // Laravel paginator: { data, links, ... }
});

const deleteAuthor = (id) => {
    if (confirm('Are you sure you want to delete this author?')) {
        router.delete(`/admin/authors/${id}`);
    }
};
</script>

<template>
    <Head title="Authors" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Authors Management</h2>
        </template>

        <div class="bg-raised shadow-xs sm:rounded-[--radius-ui]">
            <div class="p-6">
                <div class="mb-4 flex justify-end">
                    <Link href="/admin/authors/create">
                        <PrimaryButton>Add Author</PrimaryButton>
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
                                    Bio
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
                            <tr v-if="authors.data.length === 0">
                                <td colspan="3" class="text-muted px-6 py-4 text-center text-sm whitespace-nowrap">
                                    No authors found.
                                </td>
                            </tr>
                            <tr v-for="author in authors.data" :key="author.id">
                                <td class="text-content px-6 py-4 text-sm font-medium whitespace-nowrap">
                                    {{ author.name }}
                                </td>
                                <td class="text-muted px-6 py-4 text-sm whitespace-nowrap">
                                    {{ author.bio ? author.bio.substring(0, 50) + '...' : '' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <Link
                                        :href="`/admin/authors/${author.id}/edit`"
                                        class="text-accent-text mr-4 hover:underline"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        @click="deleteAuthor(author.id)"
                                        class="text-red-600 hover:underline dark:text-red-400"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="authors.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
