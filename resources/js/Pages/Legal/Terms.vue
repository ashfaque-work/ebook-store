<script setup>
import LegalLayout from '@/Pages/Legal/Layout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ lastUpdated: String });

const store = computed(() => usePage().props.store ?? {});
</script>

<template>
    <LegalLayout title="Terms of Service" :last-updated="lastUpdated">
        <p>
            These terms govern your use of {{ store.tradingName }}, operated by <strong>{{ store.legalName }}</strong
            >. By creating an account or buying a book you agree to them. If you don't, please don't use the store.
        </p>

        <h2>What we sell</h2>
        <p>
            We sell digital books. There is no physical product and nothing is shipped. When your payment succeeds, the
            book appears in your library and you can read it in your browser or download the file.
        </p>

        <h2>What you get: a licence, not ownership of the work</h2>
        <p>
            Buying a book gives you a
            <strong>personal, non-transferable, non-exclusive licence</strong>
            to read it for as long as your account exists. You do not acquire copyright in the work.
        </p>
        <p>You may:</p>
        <ul>
            <li>Read the book on any device you personally use.</li>
            <li>Download your copy and keep a personal backup.</li>
            <li>Quote short passages with attribution, as fair dealing allows.</li>
        </ul>
        <p>You may not:</p>
        <ul>
            <li>Share, resell, sublicense, rent or give away the file or your account.</li>
            <li>Upload a purchased book anywhere others can download it.</li>
            <li>Remove watermarks or any other identifying information from a file.</li>
            <li>Use the text to train a machine-learning model without written permission.</li>
        </ul>
        <p>
            Downloaded files carry your account's email address. If a copy is found circulating, we can tell whose it
            was.
        </p>

        <h2>Your account</h2>
        <p>
            You are responsible for what happens under your account, including keeping your password to yourself. Tell
            us promptly if you think someone else has access. We may suspend or close an account that is being used to
            redistribute books, to commit fraud, or to abuse other people through the store.
        </p>
        <p>
            You can delete your account at any time from your profile. Deleting it removes your access to the books in
            your library, so download anything you want to keep first.
        </p>

        <h2>Prices and payment</h2>
        <p>
            Prices are in Indian rupees and include applicable taxes unless stated otherwise. We may change a price at
            any time, but never for an order you have already paid for. Payments are processed by our payment partner;
            we never see or store your card details.
        </p>

        <h2>Availability of books</h2>
        <p>
            A book may be withdrawn from sale — a licence ends, a publisher pulls it, a title is revised. If that
            happens,
            <strong>you keep everything you have already bought.</strong>
            Withdrawn books stay in your library and remain downloadable.
        </p>

        <h2>If the store closes</h2>
        <p>
            If we ever shut down, we will give you reasonable notice and keep downloads working through that period so
            you can save your files. This is why we keep downloads available alongside the in-browser reader rather than
            locking books to our servers.
        </p>

        <h2>Things we don't promise</h2>
        <p>
            We work to keep the store available and the files intact, but we don't guarantee uninterrupted access, and
            we are not responsible for problems caused by your device, your network, or a third-party service. Nothing
            here limits liability that cannot be limited under Indian law, including under the Consumer Protection Act,
            2019.
        </p>

        <h2>Changes to these terms</h2>
        <p>
            We may update these terms. Material changes will be notified by email or a notice in the store. Continuing
            to use the store after a change means you accept the updated terms.
        </p>

        <h2>Governing law</h2>
        <p>
            These terms are governed by the laws of India. Courts at
            <strong>{{ store.jurisdiction }}</strong>
            have exclusive jurisdiction over any dispute.
        </p>

        <h2>Contact</h2>
        <p>
            Questions about these terms:
            <a :href="`mailto:${store.supportEmail}`">{{ store.supportEmail }}</a
            >. Full contact details are on the <a href="/contact">contact page</a>.
        </p>
    </LegalLayout>
</template>
