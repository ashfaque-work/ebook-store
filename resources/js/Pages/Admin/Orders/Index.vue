<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatPaise } from '@/lib/money';

const props = defineProps({
    orders: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

const apply = () => {
    router.get('/admin/orders', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch(status, apply);

const isEmpty = computed(() => props.orders.data.length === 0);

const formatDate = (value) =>
    new Date(value).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });

const statusClasses = (value) => ({
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    refunded: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
}[value] ?? 'bg-gray-100 text-gray-800');

const tiles = computed(() => [
    { label: 'Paid orders', value: props.stats.paidCount },
    { label: 'Gross revenue', value: formatPaise(props.stats.grossPaise) },
    { label: 'Last 30 days', value: formatPaise(props.stats.last30Paise) },
    { label: 'Refunded', value: formatPaise(props.stats.refundedPaise) },
    { label: 'Customers', value: props.stats.customers },
    { label: 'Awaiting payment', value: props.stats.pendingCount },
]);
</script>

<template>
    <Head title="Orders" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Orders</h2>
        </template>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div v-for="tile in tiles" :key="tile.label"
                class="rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ tile.label }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ tile.value }}</p>
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-white shadow-sm dark:bg-gray-800">
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <input v-model="search" type="search" placeholder="Order, invoice, customer…"
                    class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200" />
                <select v-model="status"
                    class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">
                    <option value="">All statuses</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Awaiting payment</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>

            <p v-if="isEmpty" class="px-4 pb-6 text-sm text-gray-500 dark:text-gray-400">
                No orders match that. Clear the filters to see everything.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 dark:text-gray-400">
                            <th scope="col" class="px-4 py-3 font-medium">Order</th>
                            <th scope="col" class="px-4 py-3 font-medium">Customer</th>
                            <th scope="col" class="px-4 py-3 font-medium">Placed</th>
                            <th scope="col" class="px-4 py-3 font-medium">Items</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Total</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="order in orders.data" :key="order.id"
                            class="text-gray-700 dark:text-gray-300">
                            <td class="whitespace-nowrap px-4 py-3">
                                <Link :href="`/admin/orders/${order.id}`"
                                    class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ order.order_number }}
                                </Link>
                                <span v-if="order.invoice_number" class="block text-xs text-gray-500">
                                    {{ order.invoice_number }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ order.user?.name }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ formatDate(order.created_at) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ order.items_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                {{ formatPaise(order.total_paise) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="statusClasses(order.status)">{{ order.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="p-4">
                    <Pagination :links="orders.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
