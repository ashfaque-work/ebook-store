<script setup>
defineProps({ type: { type: String, default: 'button' } });
</script>

<template>
    <button
        :type="type"
        class="border-line bg-raised text-content hover:bg-surface inline-flex items-center justify-center gap-2 rounded-[--radius-ui] border px-4 py-2.5 text-sm transition-colors disabled:pointer-events-none disabled:opacity-50"
    >
        <slot />
    </button>
</template>
