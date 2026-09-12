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

    <!--
      No artwork. Rather than a grey box, this is a plausible jacket: a cloth
      ground, a spine down the binding edge, and the title set as a publisher
      would set it. On a dark shelf an unlit rectangle reads as a hole in the
      page; this reads as a book whose cover simply is not very exciting.
    -->
    <div
        v-else
        role="img"
        :aria-label="alt"
        :class="[$props.class, 'book-plate relative flex flex-col justify-between overflow-hidden p-3 text-center']"
    >
        <span class="book-plate-spine" aria-hidden="true" />

        <span class="mt-4 text-xl font-bold tracking-tight text-amber-300/90">{{ initials }}</span>

        <span class="font-reading line-clamp-4 px-1 text-[0.72rem] leading-snug font-semibold text-white/85">
            {{ title }}
        </span>

        <span class="line-clamp-1 text-[0.62rem] tracking-wide text-white/45 uppercase">{{ author ?? '&nbsp;' }}</span>
    </div>
</template>
