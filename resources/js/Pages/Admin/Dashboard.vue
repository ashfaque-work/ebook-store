<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BookCover from '@/Components/BookCover.vue';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatPaise } from '@/lib/money';
import { AlertTriangle, ArrowDownRight, ArrowUpRight, BookOpen } from 'lucide-vue-next';

const props = defineProps({
    takings: { type: Object, required: true },
    catalogue: { type: Object, required: true },
    topBooks: { type: Array, default: null },
    recentOrders: { type: Array, default: null },
    beingRead: { type: Array, default: null },
});

const headline = computed(() => [
    { label: 'Gross takings', value: formatPaise(props.takings.grossPaise), hint: 'all time' },
    { label: 'Last 30 days', value: formatPaise(props.takings.last30Paise), change: props.takings.changePercent },
    { label: 'Paid orders', value: props.takings.paidOrders },
    { label: 'Customers', value: props.takings.customers },
]);

// Only what needs doing. A dashboard listing everything that is fine teaches
// people to stop reading it.
const problems = computed(() =>
    [
        props.takings.awaitingPayment
            ? {
                  text: `${props.takings.awaitingPayment} order${props.takings.awaitingPayment === 1 ? '' : 's'} awaiting payment`,
                  href: '/admin/orders?status=pending',
              }
            : null,
        props.takings.criticalReviews
            ? {
                  text: `${props.takings.criticalReviews} review${props.takings.criticalReviews === 1 ? '' : 's'} at two stars or below`,
                  href: '/admin/reviews?status=critical',
              }
            : null,
        props.catalogue.drafts
            ? {
                  text: `${props.catalogue.drafts} unpublished book${props.catalogue.drafts === 1 ? '' : 's'}`,
                  href: '/admin/books',
              }
            : null,
        props.catalogue.missingCovers
            ? {
                  text: `${props.catalogue.missingCovers} published book${props.catalogue.missingCovers === 1 ? '' : 's'} with no cover`,
                  href: '/admin/books',
              }
            : null,
        props.catalogue.missingPageCounts
            ? {
                  text: `${props.catalogue.missingPageCounts} with no length, so the reader cannot show time left`,
                  href: '/admin/books',
              }
            : null,
    ].filter(Boolean),
);

const statusClass = (status) =>
    ({
        paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    })[status] ?? 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Dashboard</h2>
        </template>

        <!-- Takings -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div
                v-for="tile in headline"
                :key="tile.label"
                class="border-line bg-raised rounded-[--radius-ui] border p-5"
            >
                <p class="text-muted text-xs tracking-wide uppercase">{{ tile.label }}</p>
                <p class="tabular text-content mt-2 text-2xl font-semibold">{{ tile.value }}</p>

                <p v-if="tile.hint" class="text-muted mt-1 text-xs">{{ tile.hint }}</p>

                <p
                    v-else-if="tile.change !== null && tile.change !== undefined"
                    class="mt-1 flex items-center gap-1 text-xs"
                    :class="tile.change >= 0 ? 'text-verdigris' : 'text-red-500'"
                >
                    <ArrowUpRight v-if="tile.change >= 0" class="size-3.5" />
                    <ArrowDownRight v-else class="size-3.5" />
                    {{ Math.abs(tile.change) }}% on the thirty days before
                </p>
            </div>
        </div>

        <!-- What needs doing -->
        <div v-if="problems.length" class="border-line bg-raised mt-6 rounded-[--radius-ui] border p-5">
            <h3 class="flex items-center gap-2 text-base font-semibold">
                <AlertTriangle class="text-accent-text size-4" />
                Worth a look
            </h3>
            <ul role="list" class="mt-3 space-y-2 text-sm">
                <li v-for="problem in problems" :key="problem.text">
                    <Link :href="problem.href" class="text-muted hover:text-content underline hover:no-underline">
                        {{ problem.text }}
                    </Link>
                </li>
            </ul>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Best sellers -->
            <section class="border-line bg-raised rounded-[--radius-ui] border p-5">
                <h3 class="text-base font-semibold">Best sellers</h3>

                <Deferred data="topBooks">
                    <template #fallback>
                        <div class="bg-line/60 mt-4 h-40 rounded" aria-hidden="true" />
                    </template>

                    <p v-if="!topBooks?.length" class="text-muted mt-3 text-sm">
                        Nothing has sold yet. This fills in with the first order.
                    </p>

                    <ul v-else role="list" class="divide-line mt-3 divide-y">
                        <li v-for="book in topBooks" :key="book.id" class="flex items-center gap-3 py-3">
                            <BookCover
                                :src="book.cover"
                                :title="book.title"
                                class="aspect-2/3 w-8 shrink-0 rounded-[--radius-cover]"
                            />
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="`/books/${book.slug}`"
                                    class="block truncate text-sm font-medium hover:underline"
                                >
                                    {{ book.title }}
                                </Link>
                                <p class="text-muted truncate text-xs">{{ book.author }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="tabular text-sm font-semibold">{{ book.sold }}</p>
                                <p class="tabular text-muted text-xs">{{ formatPaise(book.earnedPaise) }}</p>
                            </div>
                        </li>
                    </ul>
                </Deferred>
            </section>

            <!-- Recent orders -->
            <section class="border-line bg-raised rounded-[--radius-ui] border p-5">
                <div class="flex items-baseline justify-between gap-4">
                    <h3 class="text-base font-semibold">Recent orders</h3>
                    <Link href="/admin/orders" class="text-accent-text text-sm hover:underline">See all</Link>
                </div>

                <Deferred data="recentOrders">
                    <template #fallback>
                        <div class="bg-line/60 mt-4 h-40 rounded" aria-hidden="true" />
                    </template>

                    <p v-if="!recentOrders?.length" class="text-muted mt-3 text-sm">No orders yet.</p>

                    <ul v-else role="list" class="divide-line mt-3 divide-y">
                        <li v-for="order in recentOrders" :key="order.id" class="flex items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="`/admin/orders/${order.id}`"
                                    class="block truncate text-sm font-medium hover:underline"
                                >
                                    {{ order.number }}
                                </Link>
                                <p class="text-muted truncate text-xs">
                                    {{ order.customer }} &middot; {{ order.placedAt }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusClass(order.status)"
                            >
                                {{ order.status }}
                            </span>
                            <span class="tabular shrink-0 text-sm font-semibold">{{
                                formatPaise(order.totalPaise)
                            }}</span>
                        </li>
                    </ul>
                </Deferred>
            </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Catalogue -->
            <section class="border-line bg-raised rounded-[--radius-ui] border p-5">
                <h3 class="text-base font-semibold">Catalogue</h3>
                <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    <div
                        v-for="row in [
                            { label: 'Published', value: catalogue.published },
                            { label: 'Drafts', value: catalogue.drafts },
                            { label: 'Free', value: catalogue.free },
                            { label: 'Authors', value: catalogue.authors },
                            { label: 'No cover', value: catalogue.missingCovers },
                            { label: 'No length', value: catalogue.missingPageCounts },
                        ]"
                        :key="row.label"
                    >
                        <dt class="text-muted text-xs tracking-wide uppercase">{{ row.label }}</dt>
                        <dd class="tabular text-content font-semibold">{{ row.value }}</dd>
                    </div>
                </dl>

                <Link href="/admin/books" class="text-accent-text mt-4 inline-block text-sm hover:underline">
                    Manage books
                </Link>
            </section>

            <!-- Reading now -->
            <section class="border-line bg-raised rounded-[--radius-ui] border p-5">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <BookOpen class="text-muted size-4" />
                    Being read
                </h3>

                <Deferred data="beingRead">
                    <template #fallback>
                        <div class="bg-line/60 mt-4 h-28 rounded" aria-hidden="true" />
                    </template>

                    <p v-if="!beingRead?.length" class="text-muted mt-3 text-sm">
                        Nobody is part-way through a book right now.
                    </p>

                    <ul v-else role="list" class="mt-3 space-y-3">
                        <li v-for="item in beingRead" :key="item.id">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="truncate text-sm">{{ item.title }}</p>
                                <p class="tabular text-muted shrink-0 text-xs">
                                    {{ item.percent }}% &middot; {{ item.when }}
                                </p>
                            </div>
                            <div class="bg-line mt-1.5 h-1 overflow-hidden rounded-full">
                                <div class="bg-marigold h-full rounded-full" :style="{ width: `${item.percent}%` }" />
                            </div>
                        </li>
                    </ul>
                </Deferred>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
