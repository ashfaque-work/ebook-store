<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatPaise, formatPrice } from '@/lib/money';

const props = defineProps({
    order: Object,
    session: Object,
    simulation: { type: Object, default: null },
});

const page = usePage();
const processing = ref(false);
const error = ref(null);

const appName = computed(() => page.props.appName ?? 'eBook Store');
const isMock = computed(() => props.session.provider === 'fake');

/** Hand the confirmed payload to the server, which verifies it before trusting it. */
const confirm = (payload) => {
    processing.value = true;
    router.post(route('checkout.verify', props.order.id), payload, {
        onFinish: () => (processing.value = false),
        onError: () => (error.value = 'We could not confirm that payment. Nothing further has been charged.'),
    });
};

const loadRazorpay = () =>
    new Promise((resolve, reject) => {
        if (window.Razorpay) {
            return resolve();
        }
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('script blocked'));
        document.head.appendChild(script);
    });

const payWithRazorpay = async () => {
    error.value = null;

    try {
        await loadRazorpay();
    } catch {
        error.value = 'The payment window could not load. Check your connection or any ad blocker, then try again.';
        return;
    }

    const checkout = new window.Razorpay({
        key: props.session.publicKey,
        order_id: props.session.gatewayOrderId,
        amount: props.session.amountPaise,
        currency: props.session.currency,
        name: appName.value,
        description: `Order ${props.order.order_number}`,
        prefill: {
            name: props.session.prefill?.name ?? '',
            email: props.session.prefill?.email ?? '',
        },
        theme: { color: '#12172B' },
        handler: (response) => confirm(response),
        modal: {
            ondismiss: () => {
                error.value = 'Payment cancelled. Your order is saved — you can pay for it whenever you are ready.';
            },
        },
    });

    checkout.on('payment.failed', (response) => {
        error.value = response?.error?.description ?? 'That payment did not go through. Please try another method.';
    });

    checkout.open();
};

const pay = () => (isMock.value ? confirm(props.simulation) : payWithRazorpay());

/** Exercises the failure path locally: a payload that will not verify. */
const simulateFailure = () =>
    confirm({ ...props.simulation, razorpay_signature: 'deliberately-invalid-signature' });
</script>

<template>
    <Head title="Complete your payment" />

    <GuestLayout>
        <div class="mx-auto max-w-lg px-4 sm:px-6">
            <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800 sm:p-8">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Complete your payment
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Order {{ order.order_number }}
                </p>

                <ul role="list" class="mt-6 divide-y divide-gray-200 dark:divide-gray-700">
                    <li v-for="item in order.items" :key="item.id"
                        class="flex justify-between py-3 text-sm text-gray-700 dark:text-gray-300">
                        <span>{{ item.title }}</span>
                        <span>{{ formatPrice(item.price_paise) }}</span>
                    </li>
                </ul>

                <div v-if="order.tax_paise > 0"
                    class="mt-4 space-y-1 border-t border-gray-200 pt-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span>{{ formatPaise(order.subtotal_paise) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ order.tax_type === 'igst' ? 'IGST' : 'CGST + SGST' }}</span>
                        <span>{{ formatPaise(order.tax_paise) }}</span>
                    </div>
                </div>

                <div
                    class="mt-4 flex justify-between border-t border-gray-200 pt-4 text-lg font-medium text-gray-900 dark:border-gray-700 dark:text-white">
                    <span>Total</span>
                    <span>{{ formatPaise(order.total_paise) }}</span>
                </div>

                <p v-if="error" role="alert"
                    class="mt-6 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                    {{ error }}
                </p>

                <button type="button" @click="pay" :disabled="processing"
                    class="mt-6 flex w-full items-center justify-center rounded-md bg-blue-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 disabled:opacity-60">
                    {{ processing ? 'Confirming…' : `Pay ${formatPaise(order.total_paise)}` }}
                </button>

                <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                    Your card and UPI details are handled by the payment provider and never reach our servers.
                </p>

                <div v-if="isMock"
                    class="mt-6 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950">
                    <p class="font-medium text-amber-900 dark:text-amber-200">Mock gateway</p>
                    <p class="mt-1 text-amber-800 dark:text-amber-300">
                        No money moves. Set <code>RAZORPAY_KEY</code> and <code>RAZORPAY_SECRET</code> to use the real
                        one — the flow is identical either way.
                    </p>
                    <button type="button" @click="simulateFailure" :disabled="processing"
                        class="mt-3 text-amber-900 underline hover:no-underline dark:text-amber-200">
                        Simulate a declined payment
                    </button>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
