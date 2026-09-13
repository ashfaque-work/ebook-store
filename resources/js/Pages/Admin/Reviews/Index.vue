<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Star } from 'lucide-vue-next';

const props = defineProps({
    reviews: Object,
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

const apply = () => {
    router.get(
        '/admin/reviews',
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true, preserveScroll: true },
    );
};

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, 300);
});
watch(status, apply);

const hide = (review) => {
    const reason = window.prompt('Why is this being hidden? (optional, kept for the record)') ?? '';
    router.post(`/admin/reviews/${review.id}/hide`, { reason }, { preserveScroll: true });
};

const restore = (review) => router.post(`/admin/reviews/${review.id}/restore`, {}, { preserveScroll: true });

const stars = [1, 2, 3, 4, 5];
</script>

<template>
    <Head title="Reviews" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Reviews</h2>
        </template>

        <div class="border-line bg-raised rounded-[--radius-ui] border p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search reviews or book titles"
                        aria-label="Search reviews"
                        class="border-line bg-surface text-content placeholder:text-muted focus:border-marigold w-full rounded-[--radius-ui] text-sm focus:ring-0 sm:w-72"
                    />
                    <select
                        v-model="status"
                        aria-label="Filter reviews"
                        class="border-line bg-surface text-content focus:border-marigold rounded-[--radius-ui] text-sm focus:ring-0"
                    >
                        <option value="">All ({{ counts.all }})</option>
                        <option value="visible">Visible</option>
                        <option value="critical">One and two stars ({{ counts.critical }})</option>
                        <option value="hidden">Hidden ({{ counts.hidden }})</option>
                    </select>
                </div>

                <p class="text-muted shrink-0 text-sm">
                    {{ reviews.total }} review{{ reviews.total === 1 ? '' : 's' }}
                </p>
            </div>

            <ul v-if="reviews.data.length" role="list" class="divide-line mt-5 divide-y">
                <li v-for="review in reviews.data" :key="review.id" class="py-5">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <div class="flex gap-0.5" :aria-label="`${review.rating} out of 5`">
                            <Star
                                v-for="star in stars"
                                :key="star"
                                class="size-3.5"
                                :class="star <= review.rating ? 'text-marigold fill-marigold' : 'text-line'"
                            />
                        </div>

                        <Link :href="`/books/${review.book?.slug}`" class="text-sm font-medium hover:underline">
                            {{ review.book?.title }}
                        </Link>

                        <span class="text-muted text-xs"
                            >{{ review.user?.name }} &middot; {{ review.user?.email }}</span
                        >

                        <span
                            v-if="review.hidden_at"
                            class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800 dark:bg-red-900 dark:text-red-300"
                        >
                            Hidden
                        </span>
                        <span
                            v-else-if="review.verified"
                            class="text-verdigris border-verdigris/40 rounded-full border px-2 py-0.5 text-xs"
                        >
                            Verified
                        </span>
                    </div>

                    <p v-if="review.title" class="mt-2 text-sm font-semibold">{{ review.title }}</p>
                    <p v-if="review.body" class="text-muted measure mt-1 text-sm">{{ review.body }}</p>
                    <p v-if="review.hidden_reason" class="text-muted mt-2 text-xs italic">
                        Hidden because: {{ review.hidden_reason }}
                    </p>

                    <div class="mt-3 text-sm">
                        <button
                            v-if="!review.hidden_at"
                            type="button"
                            class="text-red-600 hover:underline dark:text-red-400"
                            @click="hide(review)"
                        >
                            Hide from the book page
                        </button>
                        <button v-else type="button" class="text-accent-text hover:underline" @click="restore(review)">
                            Show it again
                        </button>
                    </div>
                </li>
            </ul>

            <p v-else class="text-muted mt-6 text-sm">Nothing matches those filters.</p>

            <Pagination v-if="reviews.data.length" :links="reviews.links" />
        </div>
    </AuthenticatedLayout>
</template>
