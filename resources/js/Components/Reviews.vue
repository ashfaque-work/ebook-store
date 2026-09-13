<script setup>
import UiButton from '@/Components/Ui/UiButton.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Star } from 'lucide-vue-next';

const props = defineProps({
    bookId: { type: Number, required: true },
    bookTitle: { type: String, required: true },
    reviews: { type: Object, default: null },
    canReview: { type: Boolean, default: false },
    myReview: { type: Object, default: null },
    signedIn: { type: Boolean, default: false },
});

const writing = ref(Boolean(props.myReview));

const form = useForm({
    rating: props.myReview?.rating ?? 0,
    title: props.myReview?.title ?? '',
    body: props.myReview?.body ?? '',
});

const submit = () => {
    form.post(`/books/${props.bookId}/reviews`, {
        preserveScroll: true,
        only: ['reviews', 'myReview', 'toast', 'errors'],
        onSuccess: () => (writing.value = true),
    });
};

const remove = () => {
    router.delete(`/books/${props.bookId}/reviews`, {
        preserveScroll: true,
        only: ['reviews', 'myReview', 'toast'],
        onSuccess: () => {
            form.reset();
            writing.value = false;
        },
    });
};

// The widest bar sets the scale, so a book with three reviews still shows a
// shape rather than four invisible slivers.
const busiest = computed(() => Math.max(1, ...(props.reviews?.spread ?? []).map((row) => row.count)));

const stars = [1, 2, 3, 4, 5];
</script>

<template>
    <section id="reviews" class="border-line mt-14 border-t pt-10">
        <h2 class="text-xl">Reviews</h2>

        <!-- Summary -->
        <div v-if="reviews?.count" class="mt-5 flex flex-col gap-6 sm:flex-row sm:items-center">
            <div class="shrink-0 text-center sm:text-left">
                <p class="tabular text-4xl font-semibold">{{ reviews.average }}</p>
                <div class="mt-1 flex justify-center gap-0.5 sm:justify-start" aria-hidden="true">
                    <Star
                        v-for="star in stars"
                        :key="star"
                        class="size-4"
                        :class="star <= Math.round(reviews.average) ? 'text-marigold fill-marigold' : 'text-line'"
                    />
                </div>
                <p class="text-muted mt-1 text-xs">{{ reviews.count }} review{{ reviews.count === 1 ? '' : 's' }}</p>
            </div>

            <ul role="list" class="min-w-0 flex-1 space-y-1">
                <li v-for="row in reviews.spread" :key="row.stars" class="flex items-center gap-3 text-xs">
                    <span class="tabular text-muted w-8 shrink-0">{{ row.stars }}★</span>
                    <span class="bg-line h-1.5 min-w-0 flex-1 overflow-hidden rounded-full">
                        <span
                            class="bg-marigold block h-full rounded-full"
                            :style="{ width: `${(row.count / busiest) * 100}%` }"
                        />
                    </span>
                    <span class="tabular text-muted w-6 shrink-0 text-right">{{ row.count }}</span>
                </li>
            </ul>
        </div>

        <p v-else-if="reviews" class="text-muted mt-3 text-sm">
            No reviews yet.
            <template v-if="canReview">Yours would be the first.</template>
        </p>

        <!-- Writing one -->
        <div v-if="canReview" class="border-line bg-raised mt-8 rounded-[--radius-ui] border p-5">
            <h3 class="text-base font-semibold">
                {{ myReview ? 'Your review' : `What did you think of ${bookTitle}?` }}
            </h3>

            <p v-if="myReview?.hidden" class="mt-2 text-sm text-red-600 dark:text-red-400">
                This review is currently hidden from the book page.
            </p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <span class="text-muted text-xs tracking-wide uppercase">Rating</span>
                    <div class="mt-1 flex gap-1">
                        <button
                            v-for="star in stars"
                            :key="star"
                            type="button"
                            class="rounded p-1"
                            :aria-label="`${star} star${star === 1 ? '' : 's'}`"
                            :aria-pressed="form.rating === star"
                            @click="form.rating = star"
                        >
                            <Star
                                class="size-6 transition-colors"
                                :class="
                                    star <= form.rating ? 'text-marigold fill-marigold' : 'text-line hover:text-muted'
                                "
                            />
                        </button>
                    </div>
                    <p v-if="form.errors.rating" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.rating }}
                    </p>
                </div>

                <div>
                    <label class="text-muted text-xs tracking-wide uppercase" for="review-title">
                        Headline <span class="normal-case">(optional)</span>
                    </label>
                    <input
                        id="review-title"
                        v-model="form.title"
                        type="text"
                        maxlength="120"
                        class="border-line bg-surface text-content focus:border-marigold mt-1 block w-full rounded-[--radius-ui] text-sm focus:ring-0"
                    />
                </div>

                <div>
                    <label class="text-muted text-xs tracking-wide uppercase" for="review-body">
                        Your thoughts <span class="normal-case">(optional)</span>
                    </label>
                    <textarea
                        id="review-body"
                        v-model="form.body"
                        rows="4"
                        maxlength="4000"
                        class="border-line bg-surface text-content focus:border-marigold mt-1 block w-full rounded-[--radius-ui] text-sm focus:ring-0"
                    />
                    <p v-if="form.errors.body" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.body }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <UiButton type="submit" :disabled="form.processing || form.rating === 0">
                        {{ myReview ? 'Update review' : 'Post review' }}
                    </UiButton>
                    <button
                        v-if="myReview"
                        type="button"
                        class="text-muted hover:text-content text-sm underline hover:no-underline"
                        @click="remove"
                    >
                        Delete it
                    </button>
                </div>
            </form>
        </div>

        <p v-else-if="!signedIn" class="text-muted mt-6 text-sm">
            <Link href="/login" class="text-accent-text hover:underline">Sign in</Link>
            to review a book you have read.
        </p>

        <!-- The reviews themselves -->
        <ul v-if="reviews?.items?.length" role="list" class="divide-line mt-8 divide-y">
            <li v-for="review in reviews.items" :key="review.id" class="py-6">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <div class="flex gap-0.5" :aria-label="`${review.rating} out of 5`">
                        <Star
                            v-for="star in stars"
                            :key="star"
                            class="size-3.5"
                            :class="star <= review.rating ? 'text-marigold fill-marigold' : 'text-line'"
                        />
                    </div>
                    <span class="text-sm font-medium">{{ review.name }}</span>
                    <span
                        v-if="review.verified"
                        class="text-verdigris border-verdigris/40 rounded-full border px-2 py-0.5 text-xs"
                    >
                        Verified reader
                    </span>
                    <span class="text-muted text-xs">{{ review.when }}</span>
                </div>

                <p v-if="review.title" class="mt-2 font-semibold">{{ review.title }}</p>
                <p v-if="review.body" class="font-reading text-content measure mt-1 text-sm/relaxed">
                    {{ review.body }}
                </p>
                <p v-if="review.percentRead > 5" class="text-muted mt-2 text-xs">
                    Read {{ review.percentRead }}% of the book when this was written
                </p>
            </li>
        </ul>
    </section>
</template>
