<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatPaise, formatPrice } from '@/lib/money';

defineProps({ order: Object });

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' }) : null;
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-lg font-semibold">Order {{ order.order_number }}</h1>
        </template>

        <div class="mx-auto max-w-2xl">
            <Link href="/orders" class="text-muted hover:text-content text-sm">&larr; All orders</Link>

            <div class="border-line bg-raised mt-4 rounded-[--radius-ui] border p-6">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted">Placed</dt>
                        <dd class="mt-0.5 font-medium">{{ formatDate(order.created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-0.5 font-medium capitalize">{{ order.status }}</dd>
                    </div>
                    <div v-if="order.invoice_number">
                        <dt class="text-muted">Invoice</dt>
                        <dd class="mt-0.5 font-medium">{{ order.invoice_number }}</dd>
                    </div>
                </dl>

                <ul role="list" class="divide-line border-line mt-6 divide-y border-t">
                    <li v-for="item in order.items" :key="item.id" class="flex justify-between gap-4 py-3 text-sm">
                        <span>{{ item.title }}</span>
                        <span class="tabular shrink-0">{{ formatPrice(item.price_paise) }}</span>
                    </li>
                </ul>

                <div v-if="order.tax_paise > 0" class="border-line text-muted mt-3 space-y-1 border-t pt-3 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span class="tabular">{{ formatPaise(order.subtotal_paise) }}</span>
                    </div>
                    <div v-if="order.tax_type === 'igst'" class="flex justify-between">
                        <span>IGST</span>
                        <span class="tabular">{{ formatPaise(order.tax_paise) }}</span>
                    </div>
                    <template v-else>
                        <div class="flex justify-between">
                            <span>CGST</span>
                            <span class="tabular">{{ formatPaise(Math.round(order.tax_paise / 2)) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>SGST</span>
                            <span class="tabular">
                                {{ formatPaise(order.tax_paise - Math.round(order.tax_paise / 2)) }}
                            </span>
                        </div>
                    </template>
                </div>

                <div class="border-line mt-3 flex justify-between border-t pt-3 text-lg font-semibold">
                    <span>Total</span>
                    <span class="tabular">{{ formatPaise(order.total_paise) }}</span>
                </div>

                <div v-if="order.refunded_paise > 0" class="text-muted mt-2 flex justify-between text-sm">
                    <span>Refunded</span>
                    <span class="tabular">&minus;{{ formatPaise(order.refunded_paise) }}</span>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <UiButton href="/library">My library</UiButton>
                <UiButton href="/contact" variant="secondary">Something wrong? Tell us</UiButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
