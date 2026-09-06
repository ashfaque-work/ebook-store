<script setup>
import { Link } from '@inertiajs/vue3';
import BookCover from '@/Components/BookCover.vue';
import { formatPrice } from '@/lib/money';

defineProps({
    book: { type: Object, required: true },
    /** Owned books show a state instead of a price. */
    owned: { type: Boolean, default: false },
});
</script>

<template>
    <Link :href="`/books/${book.slug}`" prefetch cache-for="30s" class="group block">
        <BookCover
            :src="book.cover_image_path"
            :title="book.title"
            :author="book.author?.name"
            class="cover-shadow aspect-2/3 w-full rounded-[--radius-cover] transition group-hover:-translate-y-0.5"
        />

        <h3 class="font-reading text-content mt-3 text-sm/snug font-semibold">{{ book.title }}</h3>
        <p class="text-muted mt-0.5 truncate text-xs">{{ book.author?.name }}</p>
        <p class="tabular mt-1 text-xs font-semibold" :class="owned ? 'text-verdigris' : 'text-accent-text'">
            {{ owned ? 'In your library' : formatPrice(book.price_paise) }}
        </p>
    </Link>
</template>
