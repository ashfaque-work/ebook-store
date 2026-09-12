<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import BookCover from '@/Components/BookCover.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatPaise, formatPrice } from '@/lib/money';

const props = defineProps({
    cartItems: Array,
    total: Number,
});

const isEmpty = computed(() => props.cartItems.length === 0);

// False while the gateway account is in review. The checkout route turns
// people around in that window, so the button should not invite them into it.
const paymentsEnabled = computed(() => usePage().props.store.paymentsEnabled);

// Nothing to charge means nothing to hold: a free cart completes without ever
// touching the gateway, so the payments hold does not apply to it.
const isFreeCart = computed(() => props.total === 0);

const checkoutLabel = computed(() => {
    if (checkingOut.value) return isFreeCart.value ? 'Adding to your library…' : 'Taking you to payment…';
    return isFreeCart.value ? 'Add to my library' : 'Check out';
});

const clearing = ref(false);
const checkingOut = ref(false);

const checkout = () => {
    checkingOut.value = true;
    router.post(route('checkout.store'), {}, { onFinish: () => (checkingOut.value = false) });
};

const removeItem = (id) => router.delete(`/cart/${id}`, { preserveScroll: true });
const clearCart = () => router.delete('/cart');
</script>

<template>
    <Head title="Your cart" />

    <GuestLayout>
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <div class="flex items-baseline justify-between gap-4">
                <h1 class="text-2xl">Your cart</h1>
                <button
                    v-if="!isEmpty"
                    type="button"
                    @click="clearing = true"
                    class="text-muted hover:text-content text-sm"
                >
                    Clear cart
                </button>
            </div>

            <EmptyState
                v-if="isEmpty"
                class="mt-8"
                title="Your cart is empty"
                body="Books you add will show up here. Nothing is charged until you check out."
            >
                <UiButton href="/">Browse the shelves</UiButton>
            </EmptyState>

            <template v-else>
                <ul role="list" class="divide-line border-line mt-8 divide-y border-y">
                    <li v-for="item in cartItems" :key="item.id" class="flex gap-4 py-5">
                        <Link :href="`/books/${item.slug}`" class="shrink-0">
                            <BookCover
                                :src="item.cover_image_path"
                                :title="item.title"
                                class="cover-shadow aspect-2/3 w-16 rounded-[--radius-cover]"
                            />
                        </Link>

                        <div class="flex min-w-0 flex-1 flex-col justify-between">
                            <div>
                                <Link :href="`/books/${item.slug}`" class="font-semibold hover:underline">
                                    {{ item.title }}
                                </Link>
                                <p class="text-muted mt-0.5 text-sm">Instant download &middot; read in your browser</p>
                            </div>

                            <div class="mt-3 flex items-end justify-between gap-4">
                                <p class="tabular font-semibold">{{ formatPrice(item.price_paise) }}</p>
                                <button
                                    type="button"
                                    @click="removeItem(item.id)"
                                    class="text-muted hover:text-content text-sm underline hover:no-underline"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>

                <div class="border-line bg-raised mt-8 rounded-[--radius-ui] border p-6">
                    <div class="flex items-baseline justify-between text-lg font-semibold">
                        <span>Total</span>
                        <span class="tabular">{{ formatPaise(total) }}</span>
                    </div>
                    <p class="text-muted mt-1 text-sm">
                        No delivery, no waiting. Your books are in your library the moment payment clears.
                    </p>

                    <template v-if="paymentsEnabled || isFreeCart">
                        <UiButton class="mt-6" size="lg" block :disabled="checkingOut" @click="checkout">
                            {{ checkoutLabel }}
                        </UiButton>
                    </template>
                    <template v-else>
                        <UiButton class="mt-6" size="lg" block disabled>Purchasing opens shortly</UiButton>
                        <p class="text-muted mt-3 text-sm">
                            Our payment provider is completing its review. Your cart is saved — nothing here expires.
                        </p>
                    </template>
                </div>
            </template>
        </div>

        <ConfirmDialog
            v-model:open="clearing"
            title="Clear your cart?"
            body="This removes everything in it. Nothing has been charged."
            confirm-label="Clear cart"
            destructive
            @confirm="clearCart"
        />
    </GuestLayout>
</template>
