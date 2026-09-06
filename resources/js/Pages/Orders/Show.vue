<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatInr, formatPrice } from '@/lib/money';

defineProps({
    order: Object,
});

const formatDate = (value) =>
    new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Order {{ order.order_number }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 md:p-8">
                    <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>Placed {{ formatDate(order.created_at) }}</span>
                        <span class="capitalize">Status: {{ order.status }}</span>
                    </div>

                    <ul role="list" class="mt-6 divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="item in order.items" :key="item.id"
                            class="flex justify-between py-3 text-sm text-gray-700 dark:text-gray-300">
                            <span>{{ item.title }}</span>
                            <span>{{ formatPrice(item.price) }}</span>
                        </li>
                    </ul>

                    <div class="mt-4 flex justify-between border-t border-gray-200 dark:border-gray-700 pt-4 text-lg font-medium text-gray-900 dark:text-white">
                        <span>Total</span>
                        <span>{{ formatInr(order.total) }}</span>
                    </div>

                    <div class="mt-8 flex gap-3">
                        <Link :href="route('library.index')"
                            class="inline-flex items-center justify-center rounded-md bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                            My Library
                        </Link>
                        <Link :href="route('orders.index')"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            All Orders
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
