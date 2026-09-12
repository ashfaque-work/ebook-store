<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatPaise, formatPrice, paiseToRupees } from '@/lib/money';

const props = defineProps({
    order: Object,
    refundablePaise: Number,
});

const confirming = ref(false);

const form = useForm({
    amount: paiseToRupees(props.refundablePaise),
    reason: '',
});

const canRefund = computed(() => props.refundablePaise > 0);

const submit = () => {
    form.post(route('admin.orders.refund', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            form.reset('reason');
        },
    });
};

const formatDateTime = (value) =>
    value ? new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Order {{ order.order_number }}</h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <Link href="/admin/orders" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
                &larr; All orders
            </Link>

            <div class="bg-raised rounded-[--radius-ui] p-6 shadow-xs">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="text-content mt-1 font-medium capitalize">{{ order.status }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Customer</dt>
                        <dd class="text-content mt-1 font-medium">{{ order.user?.name }}</dd>
                        <dd class="text-muted text-xs">{{ order.user?.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Paid</dt>
                        <dd class="text-content mt-1 font-medium">{{ formatDateTime(order.paid_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Invoice</dt>
                        <dd class="text-content mt-1 font-medium">{{ order.invoice_number ?? '—' }}</dd>
                    </div>
                </dl>

                <p
                    v-if="order.failure_reason"
                    class="mt-4 rounded-[--radius-ui] bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300"
                >
                    {{ order.failure_reason }}
                </p>

                <ul role="list" class="divide-line mt-6 divide-y">
                    <li
                        v-for="item in order.items"
                        :key="item.id"
                        class="text-content flex justify-between py-3 text-sm"
                    >
                        <span>{{ item.title }}</span>
                        <span class="tabular-nums">{{ formatPrice(item.price_paise) }}</span>
                    </li>
                </ul>

                <div v-if="order.tax_paise > 0" class="border-line text-muted mt-4 space-y-1 border-t pt-4 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span class="tabular-nums">{{ formatPaise(order.subtotal_paise) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ order.tax_type === 'igst' ? 'IGST' : 'CGST + SGST' }}</span>
                        <span class="tabular-nums">{{ formatPaise(order.tax_paise) }}</span>
                    </div>
                </div>

                <div class="border-line text-content mt-4 flex justify-between border-t pt-4 text-lg font-medium">
                    <span>Total</span>
                    <span class="tabular-nums">{{ formatPaise(order.total_paise) }}</span>
                </div>

                <div v-if="order.refunded_paise > 0" class="text-muted mt-2 flex justify-between text-sm">
                    <span>Refunded</span>
                    <span class="tabular-nums">&minus;{{ formatPaise(order.refunded_paise) }}</span>
                </div>
            </div>

            <div v-if="order.refunds?.length" class="bg-raised rounded-[--radius-ui] p-6 shadow-xs">
                <h3 class="text-content text-sm font-semibold">Refunds</h3>
                <ul role="list" class="divide-line mt-3 divide-y text-sm">
                    <li
                        v-for="refund in order.refunds"
                        :key="refund.id"
                        class="text-content flex items-baseline justify-between gap-4 py-2"
                    >
                        <span>
                            {{ formatDateTime(refund.created_at) }}
                            <span v-if="refund.reason" class="text-muted block text-xs">{{ refund.reason }}</span>
                        </span>
                        <span class="tabular-nums">{{ formatPaise(refund.amount_paise) }}</span>
                    </li>
                </ul>
            </div>

            <div class="bg-raised rounded-[--radius-ui] p-6 shadow-xs">
                <h3 class="text-content text-sm font-semibold">Refund</h3>
                <p v-if="!canRefund" class="text-muted mt-2 text-sm">There is nothing left to refund on this order.</p>
                <template v-else>
                    <p class="text-muted mt-2 text-sm">
                        Up to {{ formatPaise(refundablePaise) }} can be returned. A full refund removes the books from
                        the customer's library.
                    </p>
                    <button
                        type="button"
                        @click="confirming = true"
                        class="mt-4 rounded-[--radius-ui] bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 focus-visible:outline-solid"
                    >
                        Refund this order
                    </button>
                </template>
            </div>
        </div>

        <Modal :show="confirming" @close="confirming = false">
            <form @submit.prevent="submit" class="p-6">
                <h2 class="text-content text-lg font-medium">Refund order {{ order.order_number }}?</h2>
                <p class="text-muted mt-1 text-sm">
                    The money goes back to the original payment method and reaches the customer in
                    {{ $page.props.store?.refundProcessingDays }}.
                </p>

                <label for="amount" class="text-content mt-4 block text-sm font-medium">Amount (₹)</label>
                <input
                    id="amount"
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :max="paiseToRupees(refundablePaise)"
                    class="border-line bg-surface text-content mt-1 block w-full rounded-[--radius-ui]"
                />
                <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>

                <label for="reason" class="text-content mt-4 block text-sm font-medium">Reason (optional)</label>
                <input
                    id="reason"
                    v-model="form.reason"
                    type="text"
                    placeholder="Wrong edition, duplicate charge…"
                    class="border-line bg-surface text-content mt-1 block w-full rounded-[--radius-ui]"
                />

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="confirming = false"
                        class="text-muted rounded-[--radius-ui] px-4 py-2 text-sm hover:underline"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-[--radius-ui] bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Refunding…' : 'Refund' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
