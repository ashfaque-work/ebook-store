<script setup>
import { onMounted, ref } from 'vue';

defineProps({ modelValue: { type: [String, Number], default: '' } });
defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        class="border-line bg-surface text-content placeholder:text-muted focus:border-marigold w-full rounded-[--radius-ui] shadow-xs focus:ring-0"
    />
</template>
