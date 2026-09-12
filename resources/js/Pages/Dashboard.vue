<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-raised overflow-hidden shadow-xs sm:rounded-[--radius-ui]">
                    <div class="text-content p-6">You're logged in!</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
