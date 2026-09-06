<script setup>
import LegalLayout from '@/Pages/Legal/Layout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ lastUpdated: String });

const store = computed(() => usePage().props.store ?? {});
</script>

<template>
    <LegalLayout title="Refunds & Cancellations" :last-updated="lastUpdated">
        <p>
            Digital books are delivered the moment you pay, so there is nothing to cancel and nothing to return. That is
            not a reason to refuse refunds, so here is a policy we can actually apply — and check — fairly.
        </p>

        <h2>When we refund in full</h2>
        <p>
            We refund your purchase in full, no questions asked, if
            <strong>all</strong>
            of the following are true:
        </p>
        <ul>
            <li>
                You ask within
                <strong>{{ store.refundWindowDays }} days</strong>
                of the purchase.
            </li>
            <li>You have not downloaded the file.</li>
            <li>
                You have read less than
                <strong>{{ store.refundMaxReadPercent }}%</strong>
                of the book.
            </li>
        </ul>
        <p>We can verify both conditions from your own account activity, so you do not have to argue your case.</p>

        <h2>We also refund, whenever it happened</h2>
        <ul>
            <li>
                <strong>You were charged twice</strong>
                for the same book.
            </li>
            <li>
                <strong>The file is broken, corrupt or the wrong book.</strong>
                Tell us and we will try to fix it first; if we can't, you get your money back.
            </li>
            <li>
                <strong>The book is materially not what the page described</strong>
                — wrong edition, wrong language, a fraction of the stated length.
            </li>
            <li>
                <strong>You were charged but never received access.</strong>
                This should not happen; if it does, it is our problem to fix.
            </li>
        </ul>

        <h2>When we normally don't refund</h2>
        <ul>
            <li>You have read most of the book and then changed your mind.</li>
            <li>You did not enjoy it. We are sorry, but taste is not a defect — read the free sample first.</li>
            <li>
                You bought a book you already owned. Check your library; the store tries to stop this, and we will fix
                it if it slips through.
            </li>
            <li>The request comes long after the purchase with no fault on our side.</li>
        </ul>
        <p>These are defaults, not walls. If your situation is genuinely unfair, write to us and say so.</p>

        <h2>How to ask</h2>
        <p>
            Email
            <a :href="`mailto:${store.supportEmail}`">{{ store.supportEmail }}</a>
            with your order number — it is on your receipt and on the orders page — and one line about what went wrong.
            You do not need a form or a template.
        </p>
        <p>
            We reply within
            <strong>{{ store.supportResponseHours }} hours</strong>.
        </p>

        <h2>How long a refund takes</h2>
        <p>
            Once approved, we send the refund to your payment provider immediately. It then takes
            <strong>{{ store.refundProcessingDays }}</strong>
            to appear on your statement, depending on your bank. That part is out of our hands.
        </p>
        <p>Refunds go back to the original payment method. We cannot redirect them elsewhere.</p>

        <h2>What happens to the book</h2>
        <p>
            A refunded book leaves your library and can no longer be read or downloaded. If you already downloaded it,
            please delete your copy — the licence ends with the refund.
        </p>

        <h2>Chargebacks</h2>
        <p>
            If something has gone wrong, please email us before raising a dispute with your bank. We can usually resolve
            it the same day, and a chargeback costs us both more time than the book was worth.
        </p>
    </LegalLayout>
</template>
