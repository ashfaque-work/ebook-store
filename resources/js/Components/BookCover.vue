<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    title: { type: String, required: true },
    author: { type: String, default: null },
    /** Tailwind classes for the box. Aspect ratio is fixed at 2:3 by default. */
    class: { type: String, default: 'w-full aspect-2/3' },
});

const failed = ref(false);

const showImage = computed(() => Boolean(props.src) && !failed.value);

/** Describe the cover for a screen reader, rather than "Book Cover". */
const alt = computed(() => (props.author ? `Cover of ${props.title} by ${props.author}` : `Cover of ${props.title}`));

/**
 * A cover is missing far more often than designs assume — seeded data, a failed
 * upload, an expired URL. Falling back to the title beats a broken-image icon.
 */
const initials = computed(() =>
    props.title
        .split(/\s+/)
        .filter((word) => /[a-z0-9]/i.test(word))
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join(''),
);
</script>

<template>
    <img
        v-if="showImage"
        :src="src"
        :alt="alt"
        loading="lazy"
        decoding="async"
        @error="failed = true"
        :class="[$props.class, 'bg-gray-200 object-cover dark:bg-gray-700']"
    />

    <div
        v-else
        role="img"
        :aria-label="alt"
        :class="[
            $props.class,
            'flex flex-col items-center justify-center gap-2 bg-gray-800 p-3 text-center dark:bg-gray-900',
        ]"
    >
        <span class="text-2xl font-bold tracking-tight text-amber-400">{{ initials }}</span>
        <span class="line-clamp-3 text-xs leading-snug font-medium text-gray-300">{{ title }}</span>
    </div>
</template>
