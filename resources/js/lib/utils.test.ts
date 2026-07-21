import { describe, expect, it } from 'vitest';
import { cn, toUrl } from './utils';

describe('cn', () => {
    it('merges conditional classes', () => {
        expect(cn('px-2', false && 'hidden', 'text-sm')).toBe('px-2 text-sm');
    });

    it('lets later tailwind utilities win over earlier conflicting ones', () => {
        expect(cn('px-2', 'px-4')).toBe('px-4');
    });
});

describe('toUrl', () => {
    it('returns a string href unchanged', () => {
        expect(toUrl('/issues')).toBe('/issues');
    });

    it('extracts the url from an object href', () => {
        expect(toUrl({ url: '/projects', method: 'get' })).toBe('/projects');
    });
});
