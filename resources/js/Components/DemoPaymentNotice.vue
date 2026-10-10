<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Says, wherever a price is shown, that the price is not real.
 *
 * The shop runs a simulated gateway while the payment account is in review,
 * so the whole of cart, checkout and invoicing can be walked through. That is
 * only honest if it is impossible to miss — a checkout that looks real and
 * takes nothing is otherwise indistinguishable from one that is broken, or
 * worse, from one that is phishing.
 *
 * Renders nothing at all when the real gateway is live.
 */
defineProps({
    /** `page` sits above the content; `inline` goes next to a buy button. */
    variant: { type: String, default: 'page' },
});

const on = computed(() => Boolean(usePage().props.store?.demoPayments));
</script>

<template>
    <div
        v-if="on"
        role="note"
        class="border-marigold/40 bg-marigold/10 text-content rounded-lg border px-4 py-3 text-sm"
        :class="variant === 'inline' ? 'mt-3' : 'mb-6'"
    >
        <p class="font-semibold">Demonstration checkout</p>
        <p class="text-muted mt-1">
            This shop is a portfolio piece. Nothing is charged, no card details are asked for, and the order you get is
            a real order against a simulated payment. The free books are genuinely free and genuinely yours to read.
        </p>
    </div>
</template>
