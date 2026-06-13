<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    theme: {
        type: String,
        default: 'light',
    },
});

const lightThemeClasses = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out'
);

const darkThemeClasses = computed(() =>
    props.active
        ? 'block w-full pl-4 pr-4 py-2 text-left text-sm font-medium text-white bg-gray-900 focus:outline-none focus:bg-gray-700 transition duration-150 ease-in-out'
        : 'block w-full pl-4 pr-4 py-2 text-left text-sm font-medium text-gray-300 hover:text-white hover:bg-gray-700 focus:outline-none focus:text-white focus:bg-gray-700 transition duration-150 ease-in-out'
);

const classes = computed(() => {
    return props.theme === 'dark' ? darkThemeClasses.value : lightThemeClasses.value;
});

</script>

<template>
    <Link :href="href" :class="classes">
    <slot />
    </Link>
</template>
