<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
    size: { type: String, default: 'md' }, // sm | md | lg
    href: { type: String, default: null },
    external: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const variants = {
    primary: 'bg-marigold text-ink hover:bg-marigold-bright font-semibold',
    secondary: 'border border-line bg-raised text-content hover:bg-surface',
    ghost: 'text-content hover:bg-line/50',
    danger: 'bg-red-600 text-white hover:bg-red-700 font-semibold',
};

const sizes = {
    sm: 'px-3 py-1.5 text-sm gap-1.5',
    md: 'px-4 py-2.5 text-sm gap-2',
    lg: 'px-6 py-3 text-base gap-2',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-[--radius-ui] transition-colors',
    'disabled:opacity-50 disabled:pointer-events-none',
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
    props.block ? 'w-full' : '',
]);

const component = computed(() => {
    if (!props.href) return 'button';
    return props.external ? 'a' : Link;
});
</script>

<template>
    <component
        :is="component"
        :class="classes"
        :href="href || undefined"
        :type="href ? undefined : type"
        :disabled="href ? undefined : disabled"
    >
        <slot />
    </component>
</template>
