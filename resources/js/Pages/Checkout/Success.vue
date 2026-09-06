<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { formatPaise, formatPrice } from '@/lib/money';

defineProps({ order: Object });
</script>

<template>
    <Head title="Order confirmed" />

    <GuestLayout>
        <div class="mx-auto max-w-lg px-4 py-14 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="bg-verdigris/15 text-verdigris grid size-10 shrink-0 place-content-center rounded-full">
                    <Check class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <h1 class="text-xl">Your books are ready</h1>
                    <p class="text-muted text-sm">Order {{ order.order_number }}</p>
                </div>
            </div>

            <div class="border-line bg-raised mt-8 rounded-[--radius-ui] border p-6">
                <ul role="list" class="divide-line divide-y">
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
                    <div class="flex justify-between">
                        <span>{{ order.tax_type === 'igst' ? 'IGST' : 'CGST + SGST' }}</span>
                        <span class="tabular">{{ formatPaise(order.tax_paise) }}</span>
                    </div>
                </div>

                <div class="border-line mt-3 flex justify-between border-t pt-3 font-semibold">
                    <span>Total paid</span>
                    <span class="tabular">{{ formatPaise(order.total_paise) }}</span>
                </div>

                <p v-if="order.invoice_number" class="text-muted mt-4 text-xs">
                    Invoice {{ order.invoice_number }} &middot; a receipt is on its way to your inbox
                </p>
            </div>

            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                <UiButton href="/library" size="lg">Start reading</UiButton>
                <UiButton href="/" variant="secondary" size="lg">Keep browsing</UiButton>
            </div>

            <p class="text-muted mt-6 text-sm">
                Something wrong with this order?
                <Link href="/contact" class="text-accent-text hover:underline">Tell us</Link>
                and we will sort it out.
            </p>
        </div>
    </GuestLayout>
</template>
