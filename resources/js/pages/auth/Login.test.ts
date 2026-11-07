import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n, setLocale } from '@/i18n';
import Login from './Login.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: {
        props: ['href'],
        template: '<a><slot /></a>',
    },
}));

function mountLogin(sso: boolean) {
    return mount(Login, {
        props: { integrations: { sso, billr: false } },
        global: {
            plugins: [i18n],
            stubs: { PasskeyVerify: true, TextLink: true },
        },
    });
}

describe('Login', () => {
    beforeEach(() => setLocale('en'));

    it('offers no SSO sign-in when the ID integration is off', () => {
        const text = mountLogin(false).text();

        expect(text).not.toContain('Sign in with Thijssensoftware');
        expect(text).toContain('Log in with a code emailed to you');
    });

    it('offers SSO sign-in when the ID integration is on', () => {
        const wrapper = mountLogin(true);

        expect(wrapper.text()).toContain('Sign in with Thijssensoftware');
        expect(wrapper.find('a[href="/auth/sso/redirect"]').exists()).toBe(
            true,
        );
    });
});
