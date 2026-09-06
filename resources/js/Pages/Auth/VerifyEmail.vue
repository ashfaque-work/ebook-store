<script setup>
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post('/email/verification-notification');
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <AuthLayout title="Verify your email">
        <Head title="Email Verification" />

        <div
            class="bg-raised mx-auto mt-6 w-full overflow-hidden px-6 py-4 shadow-md sm:max-w-md sm:rounded-[--radius-ui]"
        >
            <div class="text-muted mb-4 text-sm">
                Thanks for signing up! Before getting started, could you verify your email address by clicking on the
                link we just emailed to you? If you didn't receive the email, we will gladly send you another.
            </div>

            <div class="mb-4 text-sm font-medium text-green-600 dark:text-green-400" v-if="verificationLinkSent">
                A new verification link has been sent to the email address you provided during registration.
            </div>

            <form @submit.prevent="submit">
                <div class="mt-4 flex items-center justify-between">
                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        Resend Verification Email
                    </PrimaryButton>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="text-muted rounded-[--radius-ui] text-sm underline hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        Log Out
                    </Link>
                </div>
            </form>
        </div>
    </AuthLayout>
</template>
