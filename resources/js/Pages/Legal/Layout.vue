<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    title: { type: String, required: true },
    lastUpdated: { type: String, default: null },
});

const page = usePage();
const store = computed(() => page.props.store ?? {});

const links = [
    { href: '/terms', label: 'Terms' },
    { href: '/privacy', label: 'Privacy' },
    { href: '/refunds', label: 'Refunds' },
    { href: '/delivery', label: 'Delivery' },
    { href: '/contact', label: 'Contact' },
];
</script>

<template>
    <Head :title="title" />

    <GuestLayout>
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <nav aria-label="Policies" class="mb-8 flex flex-wrap gap-x-4 gap-y-2 text-sm">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="text-muted hover:text-content"
                    :class="{ 'text-content font-semibold': $page.url.startsWith(link.href) }"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <article class="legal bg-raised rounded-lg p-6 shadow-xs sm:p-10">
                <h1 class="text-content text-3xl font-bold tracking-tight">{{ title }}</h1>
                <p v-if="lastUpdated" class="text-muted mt-2 text-sm">
                    Last updated {{ lastUpdated }} · {{ store.legalName }}
                </p>

                <div class="mt-8">
                    <slot />
                </div>
            </article>
        </div>
    </GuestLayout>
</template>

<style scoped>
/* Local prose styles — @tailwindcss/typography isn't a dependency and these
   five pages don't justify adding one. */
.legal :deep(h2) {
    margin-top: 2.5rem;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    letter-spacing: -0.01em;
}

.legal :deep(h3) {
    margin-top: 1.75rem;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
}

.legal :deep(p),
.legal :deep(li) {
    line-height: 1.7;
    color: rgb(55 65 81);
}

.legal :deep(p) {
    margin-bottom: 1rem;
    max-width: 68ch;
}

.legal :deep(ul) {
    margin-bottom: 1rem;
    padding-left: 1.25rem;
    list-style: disc;
}

.legal :deep(li) {
    margin-bottom: 0.375rem;
}

.legal :deep(a) {
    color: rgb(37 99 235);
    text-decoration: underline;
}

.legal :deep(strong) {
    font-weight: 600;
    color: rgb(17 24 39);
}

.legal :deep(address) {
    font-style: normal;
    line-height: 1.7;
    color: rgb(55 65 81);
}

:global(.dark) .legal :deep(p),
:global(.dark) .legal :deep(li),
:global(.dark) .legal :deep(address) {
    color: rgb(209 213 219);
}

:global(.dark) .legal :deep(strong) {
    color: rgb(243 244 246);
}

:global(.dark) .legal :deep(a) {
    color: rgb(96 165 250);
}
</style>
