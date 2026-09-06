<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * A row of books on a shelf, not a grid of cards. Snap-scrolls on narrow
 * screens with the next cover half visible, which tells a person the row
 * scrolls better than any arrow button; becomes a plain row once there is
 * width enough to see everything at once.
 */
defineProps({
    heading: { type: String, required: true },
    seeAllHref: { type: String, default: null },
});
</script>

<template>
    <section class="mt-12 first:mt-0">
        <div class="mb-4 flex items-baseline justify-between gap-4">
            <h2 class="text-xl">{{ heading }}</h2>
            <Link v-if="seeAllHref" :href="seeAllHref" class="text-accent-text shrink-0 text-sm hover:underline">
                See all
            </Link>
        </div>

        <div class="shelf">
            <slot />
        </div>
    </section>
</template>
