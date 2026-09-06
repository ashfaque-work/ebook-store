<script setup>
import LegalLayout from '@/Pages/Legal/Layout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ lastUpdated: String });

const store = computed(() => usePage().props.store ?? {});
</script>

<template>
    <LegalLayout title="Privacy Policy" :last-updated="lastUpdated">
        <p>
            This policy explains what {{ store.legalName }} collects when you use
            {{ store.tradingName }}, why, and what you can do about it. We collect as little as the
            store needs to work.
        </p>

        <h2>What we collect</h2>

        <h3>You give us</h3>
        <ul>
            <li><strong>Account details</strong> — your name and email address. Your password is stored only as a cryptographic hash; we cannot read it.</li>
            <li><strong>Order details</strong> — what you bought, when, and how much you paid.</li>
            <li><strong>State of supply</strong> — the Indian state you are buying from, which tax law requires us to record on the invoice.</li>
            <li><strong>Anything you write to us</strong> — support emails and their contents.</li>
        </ul>

        <h3>Created as you use the store</h3>
        <ul>
            <li><strong>Download records</strong> — which book, when, from which IP address and browser. This is how we spot shared accounts and how we assess refund requests fairly.</li>
            <li><strong>Reading progress</strong> — your position in a book, so you can pick it up on another device. We store an opaque location marker, not the text you read.</li>
            <li><strong>Technical logs</strong> — errors and request data, kept briefly for debugging.</li>
        </ul>

        <h3>What we never collect</h3>
        <p>
            <strong>Your card, UPI or bank details never reach our servers.</strong> Payment happens
            inside our payment partner's own checkout. We receive only a payment reference, the
            amount, and whether it succeeded.
        </p>

        <h2>Why we use it</h2>
        <ul>
            <li>To sell you books and give you access to them — this is the contract between us.</li>
            <li>To issue tax-compliant invoices and keep the records Indian law requires.</li>
            <li>To prevent fraud and unauthorised redistribution — our legitimate interest, and yours.</li>
            <li>To answer your support messages.</li>
        </ul>
        <p>
            We do not sell your data, and we do not use it for advertising or profiling.
        </p>

        <h2>Who we share it with</h2>
        <p>Only the services that make the store run:</p>
        <ul>
            <li><strong>Our payment gateway</strong> — to take payments and issue refunds.</li>
            <li><strong>Our email provider</strong> — to deliver receipts and account emails.</li>
            <li><strong>Our hosting and storage providers</strong> — to run the site and store book files.</li>
            <li><strong>Our error-tracking provider</strong> — to diagnose faults.</li>
        </ul>
        <p>
            Each processes data on our instructions. We may also disclose data where the law
            requires it.
        </p>

        <h2>How long we keep it</h2>
        <ul>
            <li><strong>Account and library data</strong> — while your account is open.</li>
            <li><strong>Order and invoice records</strong> — as long as tax law requires us to retain them, even after you close your account.</li>
            <li><strong>Download logs</strong> — 24 months.</li>
            <li><strong>Technical logs</strong> — 30 days.</li>
        </ul>

        <h2>Your choices</h2>
        <ul>
            <li><strong>See your data</strong> — most of it is on your profile, library and orders pages. Ask us for the rest.</li>
            <li><strong>Correct it</strong> — edit your name and email on your profile.</li>
            <li><strong>Delete your account</strong> — from your profile, at any time. Download your books first; deletion ends your access. We keep the minimum order records tax law demands.</li>
            <li><strong>Object or complain</strong> — write to us, and you may also complain to the relevant Indian data protection authority.</li>
        </ul>
        <p>Email <a :href="`mailto:${store.supportEmail}`">{{ store.supportEmail }}</a> for any of these.</p>

        <h2>Cookies</h2>
        <p>
            We use a session cookie to keep you signed in and to remember your cart, and a local
            setting for your light/dark preference. There are no advertising or third-party tracking
            cookies on this site.
        </p>

        <h2>Security</h2>
        <p>
            Traffic is encrypted in transit. Passwords are hashed. Book files sit on private storage
            and are released only through a link that checks you own the book and expires shortly
            after it is issued. No system is perfect; if a breach affects you, we will tell you.
        </p>

        <h2>Children</h2>
        <p>
            The store is not intended for children under 13, and we do not knowingly collect their
            data.
        </p>

        <h2>Changes</h2>
        <p>
            If we change this policy materially we will update the date above and, where it matters,
            email you.
        </p>

        <h2>Contact</h2>
        <p>
            Privacy questions and requests: <a :href="`mailto:${store.supportEmail}`">{{ store.supportEmail }}</a>.
        </p>
    </LegalLayout>
</template>
