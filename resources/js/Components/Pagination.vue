<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Laravel paginator `links` array: [{ url, label, active }, ...]
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap justify-center gap-1 mt-8">
        <template v-for="(link, index) in links" :key="index">
            <span v-if="link.url === null"
                class="px-3 py-2 text-sm rounded-md text-gray-400 dark:text-gray-600 cursor-default"
                v-html="link.label" />
            <Link v-else :href="link.url" preserve-scroll
                class="px-3 py-2 text-sm rounded-md transition-colors"
                :class="link.active
                    ? 'bg-blue-600 text-white'
                    : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                v-html="link.label" />
        </template>
    </nav>
</template>
