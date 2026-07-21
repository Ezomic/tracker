import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StatTile from './StatTile.vue';

function deltaChip(props: Record<string, unknown>) {
    const wrapper = mount(StatTile, {
        props: { label: 'X', value: 1, ...props },
    });

    return wrapper.find('span.rounded-full');
}

describe('StatTile', () => {
    it('hides the delta chip when delta is null', () => {
        expect(deltaChip({ delta: null }).exists()).toBe(false);
    });

    it('prefixes a positive delta with + and appends the unit', () => {
        const chip = deltaChip({ delta: 18, deltaUnit: '%' });
        expect(chip.text()).toBe('+18%');
    });

    it('shows negative deltas without an extra sign', () => {
        expect(deltaChip({ delta: -3, deltaUnit: 'd' }).text()).toBe('-3d');
    });

    it('colours a rise green and a fall amber by default', () => {
        expect(deltaChip({ delta: 5 }).classes().join(' ')).toContain(
            'emerald',
        );
        expect(deltaChip({ delta: -5 }).classes().join(' ')).toContain('amber');
    });

    it('inverts the tone when invert is set (down is good)', () => {
        expect(
            deltaChip({ delta: -5, invert: true }).classes().join(' '),
        ).toContain('emerald');
        expect(
            deltaChip({ delta: 5, invert: true }).classes().join(' '),
        ).toContain('amber');
    });

    it('treats a zero delta as neutral', () => {
        expect(deltaChip({ delta: 0 }).classes().join(' ')).toContain('muted');
    });
});
