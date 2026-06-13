<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    book: Object,
    isBookInCart: Boolean,
});

const addToCart = () => {
    router.post(`/cart/${props.book.id}`);
};
</script>

<template>

    <Head :title="book.title" />

    <GuestLayout>
        <div class="max-w-4xl mx-auto bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 md:p-8">
            <div class="md:flex">
                <div class="md:w-1/3">
                    <img v-if="book.cover_image_path" :src="book.cover_image_path" :alt="book.title"
                        class="w-full h-auto rounded-lg shadow-lg">
                    <div v-else
                        class="w-full h-auto rounded-lg shadow-lg bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                        <span class="text-gray-500">No Image</span>
                    </div>
                </div>
                <div class="md:w-2/3 md:pl-8 mt-6 md:mt-0 flex flex-col">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ book.title }}</h1>
                    <p class="mt-2 text-lg text-gray-600 dark:text-gray-400">
                        by {{ book.author.name }} in {{ book.genre.name }}
                    </p>
                    <p class="mt-4 text-gray-700 dark:text-gray-300 flex-grow">{{ book.description }}</p>

                    <div class="mt-6 flex items-center justify-between">
                        <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">${{ book.price }}</span>

                        <PrimaryButton v-if="!isBookInCart" @click="addToCart">Add to Cart</PrimaryButton>
                        <Link v-else href="/cart">
                        <SecondaryButton>Go to Cart</SecondaryButton>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>