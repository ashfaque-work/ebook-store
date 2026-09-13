<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    BookOpen,
    ChevronDown,
    Library,
    Menu,
    LayoutDashboard,
    Moon,
    Receipt,
    ShoppingBag,
    Star,
    Sun,
    Tags,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { useTheme } from '@/Composables/useTheme';
import ToastListener from '@/Components/ToastListener.vue';

const { theme, toggleTheme } = useTheme();
const page = usePage();

const navOpen = ref(false);

const user = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => Boolean(user.value?.is_admin));

// Close the drawer on navigation, or it hangs around over the new page.
watch(
    () => page.url,
    () => (navOpen.value = false),
);

const sections = computed(() => [
    {
        heading: 'Reading',
        items: [
            { href: '/library', label: 'My library', icon: Library },
            { href: '/orders', label: 'My orders', icon: Receipt },
            { href: '/', label: 'Browse the store', icon: ShoppingBag },
        ],
    },
    ...(isAdmin.value
        ? [
              {
                  heading: 'Store',
                  items: [
                      { href: '/admin', label: 'Dashboard', icon: LayoutDashboard, exact: true },
                      { href: '/admin/orders', label: 'Orders', icon: Receipt },
                      { href: '/admin/reviews', label: 'Reviews', icon: Star },
                  ],
              },
              {
                  heading: 'Catalogue',
                  items: [
                      { href: '/admin/books', label: 'Books', icon: BookOpen },
                      { href: '/admin/authors', label: 'Authors', icon: Users },
                      { href: '/admin/genres', label: 'Genres', icon: Tags },
                  ],
              },
          ]
        : []),
]);

/*
 * A prefix match would light up every ancestor: /admin/books would mark both
 * Books and the Dashboard at /admin as current. Items whose path is a prefix
 * of another's say so, and are matched exactly.
 */
const isCurrent = (item) => {
    const href = typeof item === 'string' ? item : item.href;
    const exact = typeof item === 'string' ? href === '/' : Boolean(item.exact) || href === '/';

    return exact ? page.url === href : page.url.startsWith(href);
};
</script>

<template>
    <div class="bg-surface text-content min-h-screen">
        <ToastListener />

        <a
            href="#main"
            class="focus:bg-marigold focus:text-ink sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-[--radius-ui] focus:px-4 focus:py-2"
        >
            Skip to content
        </a>

        <div class="lg:flex">
            <!-- Sidebar, from lg up. Below that it is a drawer: the fixed
 w-64 aside used to crush the content column on a phone. -->
            <aside class="border-line bg-raised hidden w-64 shrink-0 border-e lg:block">
                <div class="sticky top-0 flex h-screen flex-col">
                    <Link href="/dashboard" class="flex h-16 items-center px-5 text-lg font-bold tracking-tight">
                        {{ $page.props.store?.tradingName ?? $page.props.appName ?? 'Bookstore' }}
                    </Link>

                    <nav aria-label="Account" class="flex-1 overflow-y-auto px-3 pb-6">
                        <template v-for="section in sections" :key="section.heading">
                            <h2 class="text-muted px-2 pt-5 pb-1 text-xs font-semibold">{{ section.heading }}</h2>
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="flex items-center gap-3 rounded-[--radius-ui] px-2 py-2 text-sm transition-colors"
                                :class="
                                    isCurrent(item)
                                        ? 'bg-marigold/15 text-content font-semibold'
                                        : 'text-muted hover:bg-line/50 hover:text-content'
                                "
                                :aria-current="isCurrent(item) ? 'page' : undefined"
                            >
                                <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                                {{ item.label }}
                            </Link>
                        </template>
                    </nav>
                </div>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="border-line bg-surface/85 sticky top-0 z-20 border-b backdrop-blur">
                    <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                        <button
                            type="button"
                            @click="navOpen = true"
                            class="text-muted hover:bg-line/50 hover:text-content rounded-[--radius-ui] p-2 lg:hidden"
                            aria-label="Open navigation"
                        >
                            <Menu class="size-5" aria-hidden="true" />
                        </button>

                        <div class="min-w-0 flex-1">
                            <slot name="header" />
                        </div>

                        <button
                            type="button"
                            @click="toggleTheme"
                            class="text-muted hover:bg-line/50 hover:text-content rounded-[--radius-ui] p-2"
                            :aria-label="theme === 'light' ? 'Switch to dark mode' : 'Switch to light mode'"
                        >
                            <Moon v-if="theme === 'light'" class="size-5" aria-hidden="true" />
                            <Sun v-else class="size-5" aria-hidden="true" />
                        </button>

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="text-muted hover:bg-line/50 hover:text-content flex items-center gap-2 rounded-[--radius-ui] px-2 py-2 text-sm"
                                >
                                    <UserRound class="size-4" aria-hidden="true" />
                                    <span class="hidden max-w-32 truncate sm:inline">{{ user?.name }}</span>
                                    <ChevronDown class="size-4" aria-hidden="true" />
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink href="/profile">Profile</DropdownLink>
                                <DropdownLink href="/logout" method="post" as="button">Sign out</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </header>

                <main id="main" class="px-4 py-6 sm:px-6 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>

        <DialogRoot v-model:open="navOpen">
            <DialogPortal>
                <DialogOverlay class="bg-ink/60 fixed inset-0 z-40 backdrop-blur-xs lg:hidden" />
                <DialogContent
                    class="border-line bg-raised fixed inset-y-0 start-0 z-50 flex w-72 max-w-[85vw] flex-col overflow-y-auto border-e p-4 lg:hidden"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <DialogTitle class="text-muted text-sm font-semibold">Navigation</DialogTitle>
                        <DialogClose
                            class="text-muted hover:bg-line/50 rounded-[--radius-ui] p-2"
                            aria-label="Close navigation"
                        >
                            <X class="size-5" aria-hidden="true" />
                        </DialogClose>
                    </div>

                    <template v-for="section in sections" :key="section.heading">
                        <h2 class="text-muted px-2 pt-4 pb-1 text-xs font-semibold">{{ section.heading }}</h2>
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-[--radius-ui] px-2 py-2.5 text-sm"
                            :class="isCurrent(item) ? 'bg-marigold/15 font-semibold' : 'text-muted hover:bg-line/50'"
                        >
                            <component :is="item.icon" class="size-4 shrink-0" aria-hidden="true" />
                            {{ item.label }}
                        </Link>
                    </template>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </div>
</template>
