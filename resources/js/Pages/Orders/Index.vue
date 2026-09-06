<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatInr, formatPrice } from '@/lib/money';

const props = defineProps({
    orders: Object, // Laravel paginator: { data, links, ... }
});

const isEmpty = computed(() => props.orders.data.length === 0);

const formatDate = (value) =>
    new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

const statusClasses = (status) => ({
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    refunded: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
}[status] ?? 'bg-gray-100 text-gray-800');
</script>

<template>
    <Head title="My Orders" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">My Orders</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                <div v-if="isEmpty" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center">
                    <p class="text-gray-600 dark:text-gray-400 text-lg">You haven't placed any orders yet.</p>
                    <Link href="/" class="mt-4 inline-block text-blue-600 dark:text-blue-400 hover:underline">
                        Browse the store
                    </Link>
                </div>

                <div v-else class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="order in orders.data" :key="order.id">
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">{{ order.order_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ order.items_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-900 dark:text-white">{{ formatInr(order.total) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full capitalize"
                                        :class="statusClasses(order.status)">{{ order.status }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <Link :href="route('orders.show', order.id)"
                                        class="text-blue-600 dark:text-blue-400 hover:underline">View</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="orders.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
