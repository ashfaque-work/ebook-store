<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Link } from '@inertiajs/vue3';

/**
 * Sign-in, register and password screens.
 *
 * They sit inside the store shell rather than on a bare page, so somebody who
 * lands on a login link keeps the way back to the catalogue.
 */
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <GuestLayout>
        <div class="mx-auto w-full max-w-md px-4 py-12 sm:px-6">
            <div class="border-line bg-raised rounded-[--radius-ui] border p-6 sm:p-8">
                <h1 v-if="title" class="text-xl">{{ title }}</h1>
                <p v-if="subtitle" class="text-muted mt-1 text-sm">{{ subtitle }}</p>

                <div :class="title ? 'mt-6' : ''">
                    <slot />
                </div>
            </div>

            <p class="text-muted mt-6 text-center text-sm">
                <Link href="/" class="hover:text-content">&larr; Back to the store</Link>
            </p>
        </div>
    </GuestLayout>
</template>
