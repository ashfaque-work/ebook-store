<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    order: Object,
});
</script>

<template>
    <Head title="Order Confirmed" />

    <GuestLayout>
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>

                <h1 class="mt-4 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Thank you for your purchase!
                </h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Order <span class="font-semibold">{{ order.order_number }}</span> &middot;
                    Total ${{ parseFloat(order.total).toFixed(2) }}
                </p>

                <ul role="list" class="mt-6 divide-y divide-gray-200 dark:divide-gray-700 text-left">
                    <li v-for="item in order.items" :key="item.id"
                        class="flex justify-between py-3 text-sm text-gray-700 dark:text-gray-300">
                        <span>{{ item.title }}</span>
                        <span>${{ parseFloat(item.price).toFixed(2) }}</span>
                    </li>
                </ul>

                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <Link :href="route('library.index')"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-blue-700">
                        Go to My Library
                    </Link>
                    <Link href="/"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 px-6 py-3 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Continue Shopping
                    </Link>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
