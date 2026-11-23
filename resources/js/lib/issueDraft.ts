import type { Issue, IssueTemplate } from '@/types';

export interface IssueDraft {
    template_id: number | null;
    title: string;
    type: Issue['type'] | null;
    priority: Issue['priority'] | null;
    description: string;
}

export type DraftError = 'unavailable' | 'tooMany' | 'invalid' | 'failed';

export type DraftResult =
    { draft: IssueDraft } | { error: DraftError; message?: string };

export interface DraftRequest {
    project_id: number;
    template_id: number | null;
    text: string;
}

export interface DraftForm {
    template: IssueTemplate | null;
    title: string;
    type: Issue['type'];
    priority: Issue['priority'];
    description: string;
}

function xsrfToken(cookie: string): string {
    const match = cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function errorFor(status: number): DraftError {
    if (status === 403) {
        return 'unavailable';
    }

    if (status === 429) {
        return 'tooMany';
    }

    return status === 422 ? 'invalid' : 'failed';
}

async function validationMessage(
    response: Response,
): Promise<string | undefined> {
    try {
        const body = await response.json();
        const first = Object.values(body?.errors ?? {})[0];

        return Array.isArray(first) && typeof first[0] === 'string'
            ? first[0]
            : undefined;
    } catch {
        return undefined;
    }
}

export async function requestIssueDraft(
    url: string,
    body: DraftRequest,
    cookie: string = document.cookie,
): Promise<DraftResult> {
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(cookie),
            },
            body: JSON.stringify(body),
        });
    } catch {
        return { error: 'failed' };
    }

    if (response.ok) {
        try {
            return { draft: (await response.json()) as IssueDraft };
        } catch {
            return { error: 'failed' };
        }
    }

    const error = errorFor(response.status);

    return error === 'invalid'
        ? { error, message: await validationMessage(response) }
        : { error };
}

/**
 * Fill the form from a draft. A template the user already chose stays; an
 * empty or missing field leaves what the form had. Claude answers "none" when
 * the text gives no hint of urgency, so that never overrides a priority the
 * form or the picked template already has.
 */
export function applyDraft(
    form: DraftForm,
    draft: IssueDraft,
    templates: IssueTemplate[],
): DraftForm {
    const picked =
        form.template ??
        templates.find((template) => template.id === draft.template_id) ??
        null;

    return {
        template: picked,
        title: draft.title || form.title,
        type: draft.type ?? form.type,
        priority:
            draft.priority && draft.priority !== 'none'
                ? draft.priority
                : form.priority !== 'none'
                  ? form.priority
                  : (picked?.priority ?? 'none'),
        description: draft.description || form.description,
    };
}
