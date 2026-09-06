<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Laravel paginator `links` array: [{ url, label, active }, ...]
    links: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="links.length > 3" aria-label="Pagination" class="mt-10 flex flex-wrap justify-center gap-1">
        <template v-for="(link, index) in links" :key="index">
            <span
                v-if="link.url === null"
                class="text-muted cursor-default rounded-[--radius-ui] px-3 py-2 text-sm opacity-50"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                class="rounded-[--radius-ui] px-3 py-2 text-sm transition-colors"
                :class="
                    link.active
                        ? 'bg-marigold text-ink font-semibold'
                        : 'text-muted hover:bg-line/50 hover:text-content'
                "
                :aria-current="link.active ? 'page' : undefined"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
