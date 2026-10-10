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
const loaded = ref(false);

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
    <!--
      The plate is always there, and the artwork arrives on top of it.

      Covers come from object storage a continent away and take the better
      part of two seconds each; a shelf of twelve was a shelf of grey
      rectangles while they came in, which is both ugly and unreadable — you
      could not tell a book that was loading from a book with no cover. Now
      the shelf is legible from the first frame and the jackets fade in over
      it. It also means a cover that never arrives degrades into something
      that still says what the book is, rather than staying grey for ever.
    -->
    <div
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

        <img
            v-if="showImage"
            :src="src"
            alt=""
            loading="lazy"
            decoding="async"
            @load="loaded = true"
            @error="failed = true"
            class="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
            :class="loaded ? 'opacity-100' : 'opacity-0'"
        />
    </div>
</template>
