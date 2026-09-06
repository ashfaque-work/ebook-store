<script setup>
defineProps({ message: { type: String, default: null } });
</script>

<template>
    <p v-show="message" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ message }}</p>
</template>
