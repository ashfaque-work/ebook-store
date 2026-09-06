<script setup>
import { useId } from 'vue';

/**
 * Label, control and error in one place. Replaces the three separate Breeze
 * components, and means no input can ship without a label or an error slot.
 */
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
});

const id = useId();
</script>

<template>
    <div>
        <label :for="id" class="text-content block text-sm font-medium">
            {{ label }}
            <span v-if="required" aria-hidden="true" class="text-accent-text">*</span>
        </label>

        <slot :id="id" :invalid="Boolean(error)" />

        <p v-if="hint && !error" class="text-muted mt-1 text-sm">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" role="alert" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </div>
</template>
