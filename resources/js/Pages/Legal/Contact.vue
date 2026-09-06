<script setup>
import LegalLayout from '@/Pages/Legal/Layout.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ lastUpdated: String });

const store = computed(() => usePage().props.store ?? {});
const address = computed(() => store.value.address ?? {});
</script>

<template>
    <LegalLayout title="Contact Us" :last-updated="lastUpdated">
        <p>
            One person reads this inbox. Write in plain words, include your order number if you have one, and you will
            get a real reply — normally within
            <strong>{{ store.supportResponseHours }} hours</strong>.
        </p>

        <h2>Email</h2>
        <p>
            <a :href="`mailto:${store.supportEmail}`">{{ store.supportEmail }}</a>
        </p>
        <p>
            Use the same address for orders, refunds, a book that won't open, a mistake on an invoice, or a privacy
            request.
        </p>

        <h2 v-if="store.supportPhone">Phone</h2>
        <p v-if="store.supportPhone">{{ store.supportPhone }}</p>

        <h2>Registered address</h2>
        <address>
            {{ store.legalName }}
            <br />
            <template v-if="address.line1">
                {{ address.line1 }}
                <br />
            </template>
            <template v-if="address.line2">
                {{ address.line2 }}
                <br />
            </template>
            {{ address.city }}
            <template v-if="address.state">, {{ address.state }}</template>
            <template v-if="address.postcode">{{ address.postcode }}</template>
            <br />
            {{ address.country }}
        </address>

        <p v-if="store.gstin" class="mt-4">
            <strong>GSTIN:</strong>
            {{ store.gstin }}
        </p>

        <h2>What to include</h2>
        <ul>
            <li>
                <strong>Order problems</strong>
                — your order number, and what you expected to happen.
            </li>
            <li>
                <strong>A book that won't open</strong>
                — the title, your device and browser, and what you see.
            </li>
            <li>
                <strong>Refunds</strong>
                — your order number. Terms are on the
                <a href="/refunds">refunds page</a>.
            </li>
            <li>
                <strong>Rights and permissions</strong>
                — tell us which book and what you want to do with it.
            </li>
        </ul>

        <h2>Reporting a copy in the wild</h2>
        <p>
            If you find one of our books being distributed without permission, send us the link. We take it seriously
            and you do not need to explain yourself.
        </p>
    </LegalLayout>
</template>
