<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Menu, Moon, ShoppingBag, Sun, X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { useTheme } from '@/Composables/useTheme';
import ToastListener from '@/Components/ToastListener.vue';

const { theme, toggleTheme } = useTheme();
const page = usePage();

const menuOpen = ref(false);

const cartCount = computed(() => page.props.cartCount ?? 0);
const user = computed(() => page.props.auth?.user ?? null);
const store = computed(() => page.props.store ?? {});

const primary = computed(() => [
    { href: '/', label: 'Browse' },
    { href: '/search-inside', label: 'Search inside' },
    ...(user.value ? [{ href: '/library', label: 'My library' }] : []),
]);

const policies = [
    { href: '/terms', label: 'Terms' },
    { href: '/privacy', label: 'Privacy' },
    { href: '/refunds', label: 'Refunds' },
    { href: '/delivery', label: 'Delivery' },
    { href: '/contact', label: 'Contact' },
];

const isCurrent = (href) => (href === '/' ? page.url === '/' : page.url.startsWith(href));
</script>

<template>
    <div class="bg-surface text-content flex min-h-screen flex-col">
        <ToastListener />

        <a
            href="#main"
            class="focus:bg-marigold focus:text-ink sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-[--radius-ui] focus:px-4 focus:py-2"
        >
            Skip to content
        </a>

        <header class="border-line bg-surface/85 sticky top-0 z-30 border-b backdrop-blur">
            <div class="mx-auto flex h-16 max-w-[1200px] items-center gap-4 px-4 sm:px-6">
                <Link href="/" class="shrink-0 text-lg font-bold tracking-tight">
                    {{ store.tradingName ?? $page.props.appName ?? 'Bookstore' }}
                </Link>

                <nav aria-label="Main" class="hidden items-center gap-1 sm:flex">
                    <Link
                        v-for="item in primary"
                        :key="item.href"
                        :href="item.href"
                        class="hover:bg-line/50 rounded-[--radius-ui] px-3 py-2 text-sm transition-colors"
                        :class="isCurrent(item.href) ? 'text-content font-semibold' : 'text-muted'"
                        :aria-current="isCurrent(item.href) ? 'page' : undefined"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ms-auto flex items-center gap-1">
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="text-muted hover:bg-line/50 hover:text-content rounded-[--radius-ui] p-2 transition-colors"
                        :aria-label="theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode'"
                    >
                        <Moon v-if="theme === 'light'" class="size-5" aria-hidden="true" />
                        <Sun v-else class="size-5" aria-hidden="true" />
                    </button>

                    <Link
                        href="/cart"
                        class="text-muted hover:bg-line/50 hover:text-content relative rounded-[--radius-ui] p-2 transition-colors"
                        :aria-label="cartCount ? `Cart, ${cartCount} items` : 'Cart, empty'"
                    >
                        <ShoppingBag class="size-5" aria-hidden="true" />
                        <span
                            v-if="cartCount > 0"
                            class="bg-marigold text-ink absolute -end-0.5 -top-0.5 grid min-w-5 place-content-center rounded-full px-1 text-[11px] font-bold"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>

                    <template v-if="user">
                        <Link
                            href="/dashboard"
                            class="text-muted hover:bg-line/50 hover:text-content hidden rounded-[--radius-ui] px-3 py-2 text-sm sm:block"
                        >
                            Account
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="text-muted hover:bg-line/50 hover:text-content hidden rounded-[--radius-ui] px-3 py-2 text-sm sm:block"
                        >
                            Sign in
                        </Link>
                        <Link
                            v-if="$page.props.canRegister"
                            href="/register"
                            class="bg-marigold text-ink hover:bg-marigold-bright hidden rounded-[--radius-ui] px-3 py-2 text-sm font-semibold sm:block"
                        >
                            Create account
                        </Link>
                    </template>

                    <button
                        type="button"
                        @click="menuOpen = true"
                        class="text-muted hover:bg-line/50 hover:text-content rounded-[--radius-ui] p-2 sm:hidden"
                        aria-label="Open menu"
                    >
                        <Menu class="size-5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            <slot />
        </main>

        <footer class="border-line mt-16 border-t">
            <div class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6">
                <nav aria-label="Policies" class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <Link
                        v-for="link in policies"
                        :key="link.href"
                        :href="link.href"
                        class="text-muted hover:text-content"
                    >
                        {{ link.label }}
                    </Link>
                </nav>
                <p class="text-muted mt-6 text-xs">
                    &copy; {{ new Date().getFullYear() }} {{ store.legalName ?? $page.props.appName ?? 'eBook Store' }}.
                    Digital books, delivered instantly.
                </p>
            </div>
        </footer>

        <!-- Mobile menu. reka-ui gives the focus trap, escape handling and
 aria wiring that a hand-rolled drawer usually forgets. -->
        <DialogRoot v-model:open="menuOpen">
            <DialogPortal>
                <DialogOverlay class="bg-ink/60 fixed inset-0 z-40 backdrop-blur-xs" />
                <DialogContent
                    class="border-line bg-surface fixed inset-y-0 end-0 z-50 flex w-72 max-w-[85vw] flex-col gap-1 border-s p-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <DialogTitle class="text-muted text-sm font-semibold">Menu</DialogTitle>
                        <DialogClose
                            class="text-muted hover:bg-line/50 rounded-[--radius-ui] p-2"
                            aria-label="Close menu"
                        >
                            <X class="size-5" aria-hidden="true" />
                        </DialogClose>
                    </div>

                    <Link
                        v-for="item in primary"
                        :key="item.href"
                        :href="item.href"
                        @click="menuOpen = false"
                        class="hover:bg-line/50 rounded-[--radius-ui] px-3 py-2.5 text-sm"
                    >
                        {{ item.label }}
                    </Link>

                    <template v-if="user">
                        <Link
                            href="/dashboard"
                            @click="menuOpen = false"
                            class="hover:bg-line/50 rounded-[--radius-ui] px-3 py-2.5 text-sm"
                        >
                            Account
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="hover:bg-line/50 rounded-[--radius-ui] px-3 py-2.5 text-start text-sm"
                        >
                            Sign out
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            @click="menuOpen = false"
                            class="hover:bg-line/50 rounded-[--radius-ui] px-3 py-2.5 text-sm"
                        >
                            Sign in
                        </Link>
                        <Link
                            v-if="$page.props.canRegister"
                            href="/register"
                            @click="menuOpen = false"
                            class="bg-marigold text-ink mt-1 rounded-[--radius-ui] px-3 py-2.5 text-center text-sm font-semibold"
                        >
                            Create account
                        </Link>
                    </template>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </div>
</template>
