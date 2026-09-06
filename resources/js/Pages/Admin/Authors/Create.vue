<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const form = useForm({
    name: '',
    bio: '',
});

const submit = () => {
    form.post('/admin/authors');
};
</script>

<template>

    <Head title="Add New Author" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Add New Author</h2>
        </template>

        <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 shadow-xs sm:rounded-lg p-6">
            <form @submit.prevent="submit">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" v-model="form.name" id="name"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"
                        required />
                    <div v-if="form.errors.name" class="text-sm text-red-600 mt-2">{{ form.errors.name }}</div>
                </div>

                <div class="mt-4">
                    <label for="bio"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Biography</label>
                    <textarea v-model="form.bio" id="bio" rows="4"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-xs"></textarea>
                    <div v-if="form.errors.bio" class="text-sm text-red-600 mt-2">{{ form.errors.bio }}</div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <Link href="/admin/authors" class="text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancel
                    </Link>
                    <PrimaryButton :disabled="form.processing">Save Author</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
