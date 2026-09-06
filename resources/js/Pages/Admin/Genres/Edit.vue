<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    genre: Object,
});

const form = useForm({
    name: props.genre.name,
});

const submit = () => {
    form.put(`/admin/genres/${props.genre.id}`);
};
</script>

<template>
    <Head title="Edit Genre" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Edit Genre</h2>
        </template>

        <div class="bg-raised mx-auto max-w-2xl p-6 shadow-xs sm:rounded-[--radius-ui]">
            <form @submit.prevent="submit">
                <div>
                    <label for="name" class="text-content block text-sm font-medium">Name</label>
                    <input
                        type="text"
                        v-model="form.name"
                        id="name"
                        class="border-line bg-surface text-content focus:border-marigold focus:ring-marigold mt-1 block w-full rounded-[--radius-ui] shadow-xs"
                        required
                    />
                    <div v-if="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</div>
                </div>

                <div class="mt-6 flex items-center justify-end">
                    <Link href="/admin/genres" class="text-muted mr-4 hover:underline">Cancel</Link>
                    <PrimaryButton :disabled="form.processing">Update Genre</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
