import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

export function updateTheme(value: Appearance) {
    document.documentElement.classList.remove('dark');
}

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

const handleSystemThemeChange = () => {
    updateTheme('light');
};

export function initializeTheme() {
    localStorage.setItem('appearance', 'light');
    updateTheme('light');
}

export function useAppearance() {
    const appearance = ref<Appearance>('light');

    onMounted(() => {
        initializeTheme();
        appearance.value = 'light';
    });

    function updateAppearance(value: Appearance) {
        appearance.value = 'light';
        localStorage.setItem('appearance', 'light');
        updateTheme('light');
    }

    return {
        appearance,
        updateAppearance,
    };
}
