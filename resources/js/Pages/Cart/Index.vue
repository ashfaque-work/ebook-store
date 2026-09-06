<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatPaise, formatPrice } from '@/lib/money';
import BookCover from '@/Components/BookCover.vue';

const props = defineProps({
    cartItems: Array, // Now receiving an array of full Book objects
    total: Number,
});

const isCartEmpty = computed(() => props.cartItems.length === 0);

const checkout = () => {
    // Unauthenticated users are redirected to login by the route middleware.
    router.post(route('checkout.store'));
};

const removeItem = (bookId) => {
    if (confirm('Are you sure you want to remove this item?')) {
        router.delete(`/cart/${bookId}`);
    }
};

const clearCart = () => {
    if (confirm('Are you sure you want to clear your entire cart?')) {
        router.delete(`/cart`);
    }
}
</script>

<template>

    <Head title="Your Shopping Cart" />

    <GuestLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Your Shopping Cart</h1>
                <button v-if="!isCartEmpty" @click="clearCart"
                    class="text-sm font-medium text-gray-500 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400">
                    Clear Cart
                </button>
            </div>


            <div v-if="isCartEmpty" class="text-center bg-white dark:bg-gray-800 rounded-lg shadow-md p-8">
                <p class="text-gray-600 dark:text-gray-400 text-lg">Your cart is empty.</p>
                <Link href="/" class="mt-4 inline-block text-blue-600 dark:text-blue-400 hover:underline">Continue
                Shopping</Link>
            </div>

            <div v-else>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="item in cartItems" :key="item.id" class="flex p-4 sm:p-6">
                            <div class="flex-shrink-0">
                                <BookCover :src="item.cover_image_path" :title="item.title"
                                    class="w-24 h-36 rounded-md" />
                            </div>
                            <div class="ml-4 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        <Link :href="`/books/${item.slug}`">{{ item.title }}</Link>
                                    </h3>
                                </div>
                                <div class="flex-1 flex items-end justify-between text-sm">
                                    <p class="text-gray-800 dark:text-gray-200 font-semibold">{{ formatPrice(item.price_paise) }}</p>
                                    <div class="flex">
                                        <button @click="removeItem(item.id)" type="button"
                                            class="font-medium text-red-600 dark:text-red-400 hover:text-red-500">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                    <div class="flex justify-between text-lg font-medium text-gray-900 dark:text-white">
                        <p>Total</p>
                        <p>{{ formatPaise(total) }}</p>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Instant digital delivery &mdash; download
                        your books right after checkout.
                    </p>
                    <div class="mt-6">
                        <button @click="checkout" type="button"
                            class="w-full flex items-center justify-center rounded-md border border-transparent bg-blue-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-blue-700">
                            Checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>