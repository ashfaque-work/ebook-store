import { ref, watchEffect } from 'vue';

export function useTheme() {
    // Initialize theme from localStorage or user's OS preference
    const theme = ref(localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));

    // Function to toggle the theme
    const toggleTheme = () => {
        theme.value = theme.value === 'light' ? 'dark' : 'light';
    };

    // Watch for changes in the theme and update the DOM and localStorage
    watchEffect(() => {
        if (theme.value === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem('theme', theme.value);
    });

    // Expose the theme and toggle function to be used in components
    return {
        theme,
        toggleTheme,
    };
}
