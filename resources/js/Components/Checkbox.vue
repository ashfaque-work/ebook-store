<script setup>
import { computed } from 'vue';

const props = defineProps({
    checked: { type: [Array, Boolean], default: false },
    value: { type: [String, Number, Boolean], default: null },
});

const emit = defineEmits(['update:checked']);

const proxyChecked = computed({
    get: () => props.checked,
    set: (val) => emit('update:checked', val),
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="border-line bg-surface text-marigold focus:ring-marigold rounded-sm shadow-xs"
    />
</template>
