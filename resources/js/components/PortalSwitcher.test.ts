import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import PortalSwitcher from './PortalSwitcher.vue';

const pageProps = vi.hoisted(() => ({
    integrations: { sso: false, billr: false },
    portalApps: [],
    portalCategories: [],
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
}));

function mountSwitcher() {
    return mount(PortalSwitcher, { global: { plugins: [i18n] } });
}

describe('PortalSwitcher', () => {
    beforeEach(() => setLocale('en'));

    it('renders nothing when the ID integration is off', () => {
        pageProps.integrations.sso = false;

        expect(mountSwitcher().find('[aria-label="Apps"]').exists()).toBe(
            false,
        );
    });

    it('renders the apps button when the ID integration is on', () => {
        pageProps.integrations.sso = true;

        expect(mountSwitcher().find('[aria-label="Apps"]').exists()).toBe(true);
    });
});
