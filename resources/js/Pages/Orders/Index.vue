<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatPaise } from '@/lib/money';

const props = defineProps({ orders: Object });

const isEmpty = computed(() => props.orders.data.length === 0);

const formatDate = (value) =>
    new Date(value).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });

const statusClasses = (status) =>
    ({
        paid: 'bg-verdigris/15 text-verdigris',
        pending: 'bg-marigold/20 text-accent-text',
        failed: 'bg-red-500/15 text-red-600 dark:text-red-400',
        refunded: 'bg-line text-muted',
    })[status] ?? 'bg-line text-muted';

const statusLabel = (status) =>
    ({
        paid: 'Paid',
        pending: 'Awaiting payment',
        failed: 'Failed',
        refunded: 'Refunded',
    })[status] ?? status;
</script>

<template>
    <Head title="My orders" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-lg font-semibold">My orders</h1>
        </template>

        <div class="mx-auto max-w-3xl">
            <EmptyState
                v-if="isEmpty"
                title="No orders yet"
                body="Every purchase you make shows up here with its receipt and invoice number."
            >
                <UiButton href="/">Browse the shelves</UiButton>
            </EmptyState>

            <ul v-else role="list" class="divide-line border-line divide-y border-y">
                <li v-for="order in orders.data" :key="order.id">
                    <Link
                        :href="`/orders/${order.id}`"
                        class="hover:bg-line/30 flex flex-wrap items-center gap-x-4 gap-y-2 py-4 transition-colors"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ order.order_number }}</p>
                            <p class="text-muted text-sm">
                                {{ formatDate(order.created_at) }} &middot; {{ order.items_count }} book{{
                                    order.items_count === 1 ? '' : 's'
                                }}
                            </p>
                        </div>

                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClasses(order.status)">
                            {{ statusLabel(order.status) }}
                        </span>

                        <p class="tabular w-24 shrink-0 text-end font-semibold">{{ formatPaise(order.total_paise) }}</p>
                    </Link>
                </li>
            </ul>

            <Pagination v-if="!isEmpty" :links="orders.links" />
        </div>
    </AuthenticatedLayout>
</template>
