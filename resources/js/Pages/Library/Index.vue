<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCover from '@/Components/BookCover.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    books: Object,
    continueReading: { type: Object, default: null },
});

const isEmpty = computed(() => props.books.data.length === 0);
</script>

<template>
    <Head title="My library" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-lg font-semibold">My library</h1>
        </template>

        <div class="mx-auto max-w-[1200px]">
            <EmptyState
                v-if="isEmpty"
                title="Nothing here yet"
                body="Books you buy land here straight away, and stay for as long as your account does."
            >
                <UiButton href="/">Browse the shelves</UiButton>
            </EmptyState>

            <template v-else>
                <!-- The first thing a returning reader should see. -->
                <Link
                    v-if="continueReading"
                    :href="route('reader.show', continueReading.book.slug)"
                    class="group border-line bg-raised hover:border-marigold flex gap-5 rounded-[--radius-ui] border p-5 transition-colors"
                >
                    <BookCover
                        :src="continueReading.book.cover_image_path"
                        :title="continueReading.book.title"
                        :author="continueReading.book.author"
                        class="cover-shadow aspect-2/3 w-20 shrink-0 rounded-[--radius-cover]"
                    />

                    <div class="flex min-w-0 flex-1 flex-col justify-center">
                        <p class="text-muted text-xs font-medium">Continue reading</p>
                        <h2 class="mt-1 truncate text-lg font-semibold">{{ continueReading.book.title }}</h2>
                        <p class="text-muted truncate text-sm">{{ continueReading.book.author }}</p>

                        <div class="bg-line mt-4 h-1.5 overflow-hidden rounded-full">
                            <div
                                class="bg-marigold h-full rounded-full"
                                :style="{ width: `${continueReading.percent}%` }"
                            />
                        </div>
                        <p class="tabular text-muted mt-2 text-xs">{{ continueReading.percent }}% read</p>
                    </div>
                </Link>

                <h2 class="text-muted mt-10 mb-4 text-sm font-semibold">Everything you own</h2>

                <div class="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-3 lg:grid-cols-5">
                    <div v-for="book in books.data" :key="book.id">
                        <Link :href="route('reader.show', book.slug)" class="group block">
                            <BookCover
                                :src="book.cover_image_path"
                                :title="book.title"
                                :author="book.author?.name"
                                class="cover-shadow aspect-2/3 w-full rounded-[--radius-cover] transition group-hover:-translate-y-0.5"
                            />
                            <h3 class="font-reading mt-3 text-sm/snug font-semibold">{{ book.title }}</h3>
                            <p class="text-muted mt-0.5 truncate text-xs">{{ book.author.name }}</p>
                        </Link>

                        <div class="mt-2 flex items-center gap-3 text-xs">
                            <Link
                                :href="route('reader.show', book.slug)"
                                class="text-accent-text font-semibold hover:underline"
                            >
                                Read
                            </Link>
                            <a :href="route('library.download', book.id)" class="text-muted hover:text-content">
                                Download
                            </a>
                        </div>
                    </div>
                </div>

                <Pagination :links="books.links" />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
