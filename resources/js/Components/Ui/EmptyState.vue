<script setup>
/** An empty screen is an invitation to act, not an apology. */
defineProps({
    title: { type: String, required: true },
    body: { type: String, default: null },
});
</script>

<template>
    <div class="border-line bg-raised rounded-[--radius-ui] border px-6 py-14 text-center">
        <h2 class="text-content text-lg font-semibold">{{ title }}</h2>
        <p v-if="body" class="text-muted mx-auto mt-2 max-w-sm text-sm">{{ body }}</p>
        <div class="mt-6 flex justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
