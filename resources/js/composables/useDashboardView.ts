import type { Ref } from 'vue';
import { onMounted, ref } from 'vue';
import type { DashboardView } from '@/types';

const STORAGE_KEY = 'dashboard-view';
const DEFAULT_VIEW: DashboardView = 'focus';
const views: DashboardView[] = ['focus', 'metrics', 'board'];

export type UseDashboardViewReturn = {
    view: Ref<DashboardView>;
    setView: (value: DashboardView) => void;
};

export function isDashboardView(value: unknown): value is DashboardView {
    return typeof value === 'string' && views.includes(value as DashboardView);
}

function storedView(): DashboardView {
    if (typeof window === 'undefined') {
        return DEFAULT_VIEW;
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return isDashboardView(stored) ? stored : DEFAULT_VIEW;
}

const view = ref<DashboardView>(DEFAULT_VIEW);

export function useDashboardView(): UseDashboardViewReturn {
    onMounted(() => {
        view.value = storedView();
    });

    function setView(value: DashboardView) {
        view.value = value;

        if (typeof window !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, value);
        }
    }

    return { view, setView };
}
