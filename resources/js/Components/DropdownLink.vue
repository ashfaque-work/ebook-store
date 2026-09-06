<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        class="text-content hover:bg-line/50 focus:bg-line/50 block w-full px-4 py-2 text-start text-sm transition-colors focus:outline-hidden"
    >
        <slot />
    </Link>
</template>
