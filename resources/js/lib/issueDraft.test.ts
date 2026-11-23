import { afterEach, describe, expect, it, vi } from 'vitest';
import type { IssueTemplate } from '@/types';
import type { DraftForm, IssueDraft } from './issueDraft';
import { applyDraft, requestIssueDraft } from './issueDraft';

function template(id: number): IssueTemplate {
    return {
        id,
        name: `Template ${id}`,
        description: '## Steps',
        type: 'fix',
        priority: 'high',
        labelIds: [],
        cadence: 'none',
        nextRunAt: null,
        targetProjectId: null,
    } as IssueTemplate;
}

const draft: IssueDraft = {
    template_id: 2,
    title: 'Login fails',
    type: 'fix',
    priority: 'urgent',
    description: '## Steps\nClick login.',
};

const blankForm: DraftForm = {
    template: null,
    title: '',
    type: 'feature',
    priority: 'none',
    description: '',
};

function respond(status: number, body: unknown) {
    const fetchMock = vi.fn().mockResolvedValue(
        new Response(JSON.stringify(body), {
            status,
            headers: { 'Content-Type': 'application/json' },
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

const request = { project_id: 7, template_id: null, text: 'it broke' };

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('requestIssueDraft', () => {
    it('posts the text as JSON with the CSRF token from the cookie', async () => {
        const fetchMock = respond(200, draft);

        await requestIssueDraft(
            '/issues/draft',
            request,
            'other=1; XSRF-TOKEN=abc%3D%3D; last=2',
        );

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/issues/draft');
        expect(init.method).toBe('POST');
        expect(init.credentials).toBe('same-origin');
        expect(init.headers['X-XSRF-TOKEN']).toBe('abc==');
        expect(init.headers.Accept).toBe('application/json');
        expect(init.headers['Content-Type']).toBe('application/json');
        expect(JSON.parse(init.body)).toEqual(request);
    });

    it('sends an empty token when there is no cookie', async () => {
        const fetchMock = respond(200, draft);

        await requestIssueDraft('/issues/draft', request, '');

        expect(fetchMock.mock.calls[0][1].headers['X-XSRF-TOKEN']).toBe('');
    });

    it('returns the draft on success', async () => {
        respond(200, draft);

        expect(await requestIssueDraft('/issues/draft', request, '')).toEqual({
            draft,
        });
    });

    it.each([
        [403, 'unavailable'],
        [429, 'tooMany'],
        [502, 'failed'],
        [500, 'failed'],
    ])('maps status %i to %s', async (status, error) => {
        respond(status, { message: 'server text' });

        expect(await requestIssueDraft('/issues/draft', request, '')).toEqual({
            error,
        });
    });

    it('passes the first validation message along', async () => {
        respond(422, {
            message:
                'The text field must not be greater than 10000 characters.',
            errors: {
                text: [
                    'The text field must not be greater than 10000 characters.',
                ],
            },
        });

        expect(await requestIssueDraft('/issues/draft', request, '')).toEqual({
            error: 'invalid',
            message:
                'The text field must not be greater than 10000 characters.',
        });
    });

    it('reports an invalid request without a message when the body has none', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response('nope', { status: 422 })),
        );

        expect(await requestIssueDraft('/issues/draft', request, '')).toEqual({
            error: 'invalid',
            message: undefined,
        });
    });

    it('reports a network failure as failed', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError()));

        expect(await requestIssueDraft('/issues/draft', request, '')).toEqual({
            error: 'failed',
        });
    });
});

describe('applyDraft', () => {
    it('fills every field and picks the template Claude chose', () => {
        expect(
            applyDraft(blankForm, draft, [template(1), template(2)]),
        ).toEqual({
            template: template(2),
            title: 'Login fails',
            type: 'fix',
            priority: 'urgent',
            description: '## Steps\nClick login.',
        });
    });

    it('keeps the template the user already chose', () => {
        const form = { ...blankForm, template: template(1) };

        expect(
            applyDraft(form, draft, [template(1), template(2)]).template,
        ).toEqual(template(1));
    });

    it('leaves the template empty when Claude picked none or an unknown one', () => {
        expect(
            applyDraft(blankForm, { ...draft, template_id: null }, [
                template(2),
            ]).template,
        ).toBeNull();
        expect(
            applyDraft(blankForm, { ...draft, template_id: 9 }, [template(2)])
                .template,
        ).toBeNull();
    });

    it('keeps what the form had where the draft is empty', () => {
        const form: DraftForm = {
            template: null,
            title: 'Mine',
            type: 'fix',
            priority: 'low',
            description: 'My notes',
        };

        expect(
            applyDraft(
                form,
                {
                    template_id: null,
                    title: '',
                    type: null,
                    priority: null,
                    description: '',
                },
                [],
            ),
        ).toEqual(form);
    });
});
