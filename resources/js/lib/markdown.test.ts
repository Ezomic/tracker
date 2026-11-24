// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { renderMarkdown } from './markdown';

function parse(source: string): HTMLElement {
    const root = document.createElement('div');
    root.innerHTML = renderMarkdown(source);

    return root;
}

describe('renderMarkdown', () => {
    it('renders markdown to html', () => {
        const root = parse('# Title\n\n- one\n- two\n\n**bold**');

        expect(root.querySelector('h1')?.textContent).toBe('Title');
        expect(root.querySelectorAll('li')).toHaveLength(2);
        expect(root.querySelector('strong')?.textContent).toBe('bold');
    });

    it('renders an empty string for empty or whitespace-only input', () => {
        expect(renderMarkdown('').trim()).toBe('');
        expect(renderMarkdown('  \n ').trim()).toBe('');
    });

    it('strips script tags', () => {
        const root = parse('before <script>window.pwned = 1</script> after');

        expect(root.querySelector('script')).toBeNull();
        expect(root.textContent).not.toContain('window.pwned');
    });

    it('strips inline event handlers', () => {
        const root = parse('<img src="x" onerror="window.pwned = 1">');

        expect(root.querySelector('img')?.getAttribute('onerror')).toBeNull();
    });

    it('drops javascript: links', () => {
        const root = parse(
            '[click](javascript:alert(1))\n\n<a href="javascript:alert(1)">raw</a>',
        );

        for (const link of root.querySelectorAll('a')) {
            expect(link.getAttribute('href') ?? '').not.toMatch(
                /^javascript:/i,
            );
        }
    });

    it('removes iframes, forms and style blocks', () => {
        const root = parse(
            '<iframe src="https://example.com"></iframe><form action="/x"><input></form><style>body{display:none}</style>',
        );

        expect(root.querySelector('iframe')).toBeNull();
        expect(root.querySelector('style')).toBeNull();
        expect(root.querySelector('form')).toBeNull();
        expect(root.querySelector('input')).toBeNull();
    });

    it("drops class and id so a note cannot borrow the app's utility classes or clobber its ids", () => {
        const root = parse(
            '<div class="fixed inset-0 z-50 bg-white" id="note-title">cover</div>',
        );

        expect(root.querySelector('div')?.getAttribute('class')).toBeNull();
        expect(root.querySelector('div')?.getAttribute('id')).toBeNull();
    });

    it('drops inline style attributes', () => {
        const root = parse('<p style="position:fixed;inset:0">cover</p>');

        expect(root.querySelector('p')?.getAttribute('style')).toBeNull();
    });

    it('keeps code fences as text, not markup', () => {
        const root = parse('```\n<script>alert(1)</script>\n```');

        expect(root.querySelector('script')).toBeNull();
        expect(root.querySelector('pre code')?.textContent).toContain(
            '<script>alert(1)</script>',
        );
    });

    it('handles unicode and emoji', () => {
        expect(parse('数据库 🚀 café').textContent).toContain('数据库 🚀 café');
    });
});
