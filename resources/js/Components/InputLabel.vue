<script setup>
defineProps({ value: { type: String, default: null } });
</script>

<template>
    <label class="text-content block text-sm font-medium">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
