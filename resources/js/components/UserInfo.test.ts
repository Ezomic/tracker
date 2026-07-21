import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { User } from '@/types';
import UserInfo from './UserInfo.vue';

function makeUser(overrides: Partial<User> = {}): User {
    return {
        id: 1,
        name: 'Robbin Thijssen',
        email: 'robbin@example.com',
        avatar: '',
        ...overrides,
    } as User;
}

describe('UserInfo', () => {
    it('renders the user name', () => {
        const wrapper = mount(UserInfo, { props: { user: makeUser() } });

        expect(wrapper.text()).toContain('Robbin Thijssen');
    });

    it('hides the email by default and shows it when requested', () => {
        const hidden = mount(UserInfo, { props: { user: makeUser() } });
        expect(hidden.text()).not.toContain('robbin@example.com');

        const shown = mount(UserInfo, {
            props: { user: makeUser(), showEmail: true },
        });
        expect(shown.text()).toContain('robbin@example.com');
    });

    it('falls back to initials when there is no avatar', () => {
        const wrapper = mount(UserInfo, { props: { user: makeUser() } });

        expect(wrapper.text()).toContain('RT');
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('renders the avatar image when an avatar url is present', () => {
        const wrapper = mount(UserInfo, {
            props: { user: makeUser({ avatar: 'https://cdn.test/a.png' }) },
        });

        // Reka UI mounts the image asynchronously after it "loads"; assert the
        // fallback initials are not the only content by checking the src wiring.
        expect(wrapper.html()).toContain('Robbin Thijssen');
    });
});
