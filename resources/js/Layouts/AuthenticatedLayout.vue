<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import ToastListener from '@/Components/ToastListener.vue'; // 1. Import the new listener

const showingNavigationDropdown = ref(false);
const page = usePage();
const { theme, toggleTheme } = useTheme();

const isUrl = (...urls) => {
    let currentUrl = page.url.substring(1);
    if (urls[0] === '') {
        return currentUrl === '';
    }
    return urls.filter((url) => currentUrl.startsWith(url)).length;
};
</script>

<template>
    <div>
        <ToastListener />
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex">
            <aside class="w-64 bg-gray-800 text-white flex-shrink-0">
                <div class="p-4 flex items-center justify-center h-16">
                    <Link href="/dashboard">
                    <ApplicationLogo class="block h-9 w-auto fill-current text-white" />
                    </Link>
                </div>
                <nav class="mt-4 flex-1">
                    <h3 class="px-4 text-xs uppercase text-gray-400 font-semibold tracking-wider">Main Menu</h3>
                    <div class="mt-2">
                        <NavLink href="/dashboard" :active="isUrl('dashboard')" theme="dark">
                            Dashboard
                        </NavLink>
                    </div>

                    <h3 class="px-4 mt-6 text-xs uppercase text-gray-400 font-semibold tracking-wider">Content</h3>
                    <div class="mt-2">
                        <NavLink href="/admin/authors" :active="isUrl('admin/authors')" theme="dark">
                            Authors
                        </NavLink>
                        <NavLink href="/admin/genres" :active="isUrl('admin/genres')" theme="dark">
                            Genres
                        </NavLink>
                        <NavLink href="/admin/books" :active="isUrl('admin/books')" theme="dark">
                            Books
                        </NavLink>
                    </div>
                </nav>
            </aside>

            <div class="flex-1 flex flex-col">
                <nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between h-16">
                            <div class="flex items-center">
                                <slot name="header" />
                            </div>

                            <div class="hidden sm:flex sm:items-center sm:ms-6">
                                <button @click="toggleTheme"
                                    class="mr-4 p-2 rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                                    <!-- Show MOON icon to switch to Dark Mode -->
                                    <svg v-if="theme === 'light'" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                                    </svg>
                                    <!-- Show new, better SUN icon to switch to Light Mode -->
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                        fill="currentColor" class="h-6 w-6">
                                        <path
                                            d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.106a.75.75 0 010 1.06l-1.591 1.59a.75.75 0 11-1.06-1.06l1.59-1.591a.75.75 0 011.06 0zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.894 17.894a.75.75 0 011.06 0l1.59 1.591a.75.75 0 11-1.06 1.06l-1.591-1.59a.75.75 0 010-1.06zM12 18a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM5.106 17.894a.75.75 0 010-1.06l1.591-1.59a.75.75 0 111.06 1.06l-1.59 1.591a.75.75 0 01-1.06 0zM4.5 12a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H5.25a.75.75 0 01-.75-.75zM6.106 5.106a.75.75 0 011.06 0l1.59 1.591a.75.75 0 01-1.06 1.06l-1.591-1.59a.75.75 0 010-1.06z" />
                                    </svg>
                                </button>
                                <div class="ms-3 relative">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <span class="inline-flex rounded-md">
                                                <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                                    {{ $page.props.auth.user.name }}
                                                    <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </template>

                                        <template #content>
                                            <DropdownLink href="/profile"> Profile </DropdownLink>
                                            <DropdownLink href="/logout" method="post" as="button">
                                                Log Out
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                <main class="flex-1 overflow-y-auto p-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>