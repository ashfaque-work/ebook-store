<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UiButton from '@/Components/Ui/UiButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AlertTriangle, Check } from 'lucide-vue-next';

const props = defineProps({
    config: { type: Object, required: true },
    problems: { type: Array, default: () => [] },
    usesSmtp: { type: Boolean, default: false },
    hasApiKey: { type: Boolean, default: false },
});

const page = usePage();

const form = useForm({ email: page.props.auth?.user?.email ?? '' });

const send = () => form.post('/admin/mail/test', { preserveScroll: true });

// An API mailer has no host, port or password, and showing empty rows for
// them invites someone to go and fill them in.
const rows = computed(() =>
    [
        { label: 'Mailer', value: props.config.mailer },
        props.usesSmtp ? { label: 'Host', value: props.config.host || '—' } : null,
        props.usesSmtp ? { label: 'Port', value: props.config.port || '—' } : null,
        props.usesSmtp ? { label: 'Username', value: props.config.username || '—' } : null,
        props.usesSmtp ? { label: 'Password', value: props.config.hasPassword ? 'set' : 'not set' } : null,
        props.usesSmtp ? null : { label: 'API key', value: props.hasApiKey ? 'set' : 'not set' },
        { label: 'From', value: `${props.config.fromName} <${props.config.fromAddress}>` },
    ].filter(Boolean),
);
</script>

<template>
    <Head title="Email" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-content text-xl leading-tight font-semibold">Email</h2>
        </template>

        <div class="max-w-2xl">
            <div class="border-line bg-raised rounded-[--radius-ui] border p-5">
                <h3 class="text-base font-semibold">What the store is configured to do</h3>

                <dl class="divide-line mt-4 divide-y text-sm">
                    <div v-for="row in rows" :key="row.label" class="flex justify-between gap-4 py-2">
                        <dt class="text-muted">{{ row.label }}</dt>
                        <dd class="tabular text-content text-right break-all">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>

            <!-- What is wrong, if anything -->
            <div v-if="problems.length" class="border-line bg-raised mt-6 rounded-[--radius-ui] border p-5">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <AlertTriangle class="text-accent-text size-4" />
                    Worth fixing
                </h3>
                <ul role="list" class="text-muted mt-3 space-y-2 text-sm">
                    <li v-for="problem in problems" :key="problem">{{ problem }}</li>
                </ul>
            </div>

            <div v-else class="border-line bg-raised mt-6 rounded-[--radius-ui] border p-5">
                <p class="text-verdigris flex items-center gap-2 text-sm">
                    <Check class="size-4" />
                    Nothing obviously wrong with the configuration.
                </p>
            </div>

            <!-- Prove it -->
            <div class="border-line bg-raised mt-6 rounded-[--radius-ui] border p-5">
                <h3 class="text-base font-semibold">Send a test</h3>
                <p class="text-muted mt-1 text-sm">
                    The only way to know. A receipt that never arrives looks exactly like one nobody looked for.
                </p>

                <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="send">
                    <div class="min-w-0 flex-1">
                        <label class="text-muted text-xs tracking-wide uppercase" for="test-email">Send to</label>
                        <input
                            id="test-email"
                            v-model="form.email"
                            type="email"
                            class="border-line bg-surface text-content focus:border-marigold mt-1 block w-full rounded-[--radius-ui] text-sm focus:ring-0"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <UiButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Sending…' : 'Send test email' }}
                    </UiButton>
                </form>
            </div>

            <p class="text-muted mt-6 text-sm">
                Changing any of this means changing the environment variables on the host and letting it redeploy —
                there is no setting here, on purpose: credentials belong in the environment, not in a database.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
