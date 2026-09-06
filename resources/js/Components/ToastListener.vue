<script setup>
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const page = usePage();
const toast = useToast();

// Watch for the 'toast' prop to change on subsequent page visits
watch(
    () => page.props.toast,
    (toastData) => {
        if (toastData) {
            toast(toastData.message, { type: toastData.type });
        }
    },
);

// This handles the case where the component is mounted on a page
// that already has a toast message (less common but good practice)
onMounted(() => {
    if (page.props.toast) {
        toast(page.props.toast.message, { type: page.props.toast.type });
    }
});
</script>

<template>
    <!-- This component renders nothing to the DOM -->
</template>
