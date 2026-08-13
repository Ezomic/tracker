<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\StatusCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The API's counterpart to FilterIssuesRequest. The web form posts database
 * ids; API callers only know keys, names and emails, so the filters are
 * expressed in those terms instead.
 */
class FilterIssuesApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project' => ['sometimes', 'string', 'exists:projects,key'],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(IssueStatus::class)],
            'type' => ['sometimes', Rule::enum(IssueType::class)],
            'priority' => ['sometimes', Rule::enum(IssuePriority::class)],
            'label' => ['sometimes', 'string', 'max:255'],
            'assignee' => ['sometimes', 'string', 'max:255'],
            'parent' => ['sometimes', 'string', 'max:255'],
            'source' => ['sometimes', 'string', 'max:64'],
            'workflow_state' => ['sometimes', 'string', 'max:255'],
            'state_category' => ['sometimes', Rule::enum(StatusCategory::class)],
            'archived' => ['sometimes', 'string', Rule::in(['exclude', 'include', 'only'])],
            'stale' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Archived issues stay out unless asked for, as they always have.
     */
    public function archived(): string
    {
        return $this->string('archived')->toString() ?: 'exclude';
    }

    public function perPage(): int
    {
        return $this->integer('per_page') ?: 50;
    }
}
