<script setup>
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';

/**
 * Replaces window.confirm(), which cannot be styled, cannot be read properly
 * by assistive tech, and looks like the browser is warning you about the site.
 */
defineProps({
    title: { type: String, required: true },
    body: { type: String, default: null },
    confirmLabel: { type: String, default: 'Confirm' },
    destructive: { type: Boolean, default: false },
});

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['confirm']);
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="bg-ink/60 fixed inset-0 z-40 backdrop-blur-xs" />
            <AlertDialogContent
                class="border-line bg-raised fixed top-1/2 left-1/2 z-50 w-[min(28rem,92vw)] -translate-x-1/2 -translate-y-1/2 rounded-[--radius-ui] border p-6 shadow-xl"
            >
                <AlertDialogTitle class="text-content text-lg font-semibold">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="body" class="text-muted mt-2 text-sm">
                    {{ body }}
                </AlertDialogDescription>

                <div class="mt-6 flex justify-end gap-3">
                    <AlertDialogCancel class="text-muted hover:bg-line/50 rounded-[--radius-ui] px-4 py-2 text-sm">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        @click="emit('confirm')"
                        class="rounded-[--radius-ui] px-4 py-2 text-sm font-semibold"
                        :class="
                            destructive
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : 'bg-marigold text-ink hover:bg-marigold-bright'
                        "
                    >
                        {{ confirmLabel }}
                    </AlertDialogAction>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
