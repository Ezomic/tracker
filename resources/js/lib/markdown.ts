import DOMPurify from 'dompurify';
import { marked } from 'marked';

// Scripts and handlers are DOMPurify's default; styles and form controls are
// allowed by default but let a note restyle the whole page or fake a login form.
const FORBIDDEN_TAGS = [
    'style',
    'form',
    'input',
    'button',
    'textarea',
    'select',
];

export function renderMarkdown(source: string): string {
    return DOMPurify.sanitize(marked.parse(source, { async: false }), {
        FORBID_TAGS: FORBIDDEN_TAGS,
        FORBID_ATTR: ['style', 'class', 'id', 'name'],
    });
}
