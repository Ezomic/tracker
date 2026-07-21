import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Sparkline from './Sparkline.vue';

describe('Sparkline', () => {
    it('renders nothing when there are no values', () => {
        const wrapper = mount(Sparkline, { props: { values: [] } });
        expect(wrapper.find('polyline').exists()).toBe(false);
    });

    it('centers a single value', () => {
        const wrapper = mount(Sparkline, {
            props: { values: [5], width: 120, height: 28 },
        });
        const points = wrapper.get('polyline').attributes('points');
        // one point, at the horizontal centre
        expect(points).toMatch(/^60\.0,/);
        expect(points!.trim().split(' ')).toHaveLength(1);
    });

    it('spreads multiple points across the width and inverts the y axis', () => {
        const wrapper = mount(Sparkline, {
            props: { values: [0, 10], width: 100, height: 28 },
        });
        const coords = wrapper
            .get('polyline')
            .attributes('points')!
            .trim()
            .split(' ')
            .map((p) => p.split(',').map(Number));

        expect(coords).toHaveLength(2);
        expect(coords[0][0]).toBe(0); // first x at 0
        expect(coords[1][0]).toBe(100); // last x at width
        // lower value sits lower on the screen (higher y) than the higher value
        expect(coords[0][1]).toBeGreaterThan(coords[1][1]);
    });
});
