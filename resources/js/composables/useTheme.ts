import { useStorage } from '@vueuse/core';
import { watch, onMounted } from 'vue';

export type Theme = 'dark' | 'light';

export function useTheme() {
    const theme = useStorage<Theme>('ruangasn-theme', 'dark');

    const apply = (value: Theme) => {
        document.documentElement.setAttribute('data-theme', value);
    };

    onMounted(() => apply(theme.value));
    watch(theme, apply);

    const toggle = () => {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    };

    const isDark = () => theme.value === 'dark';

    return { theme, toggle, isDark };
}
