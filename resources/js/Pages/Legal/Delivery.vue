<script setup>
import LegalLayout from '@/Pages/Legal/Layout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ lastUpdated: String });

const store = computed(() => usePage().props.store ?? {});
</script>

<template>
    <LegalLayout title="Delivery" :last-updated="lastUpdated">
        <p>
            Everything {{ store.tradingName }} sells is a digital book.
            <strong>Nothing is shipped, and there are no delivery charges.</strong>
        </p>

        <h2>How and when you get your book</h2>
        <p>
            Delivery is immediate. The moment your payment is confirmed, the book appears in
            <strong>My Library</strong>
            and you can start reading in your browser or download the file. There is no waiting period, no dispatch, and
            no tracking number, because there is nothing in transit.
        </p>
        <p>
            A receipt goes to your registered email address as well. If the email does not arrive, it does not affect
            your access — the book is already in your library.
        </p>

        <h2>Where we deliver</h2>
        <p>Anywhere you can open the site. A digital book has no delivery area.</p>

        <h2>What you need</h2>
        <ul>
            <li>An account on the store, and the email address you used to buy.</li>
            <li>A current browser for reading in-page.</li>
            <li>Any PDF or EPUB reader, if you prefer to download.</li>
        </ul>

        <h2>How long you keep access</h2>
        <p>
            Your library is permanent for as long as your account exists. A book withdrawn from sale stays in the
            library of everyone who bought it. Deleting your account, or a refund, ends access — so download anything
            you want to keep first.
        </p>

        <h2>If something goes wrong</h2>
        <p>
            If a payment succeeded but the book has not appeared, first reload
            <strong>My Library</strong>. If it is still missing, email
            <a :href="`mailto:${store.supportEmail}`">{{ store.supportEmail }}</a>
            with your order number and we will fix it — usually within {{ store.supportResponseHours }} hours. You will
            not be charged twice for a book that never arrived, and you will not need to buy it again.
        </p>
        <p>
            Refund terms are on the
            <a href="/refunds">refunds page</a>.
        </p>
    </LegalLayout>
</template>
