<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\IssueTemplate;
use App\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['type', 'priority'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->for($this->user())?->id;

        /** @var IssueTemplate|null $template */
        $template = $this->route('template');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('issue_templates', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($template?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', Rule::enum(IssueType::class)],
            'priority' => ['nullable', Rule::enum(IssuePriority::class)],
            'labels' => ['array'],
            'labels.*' => [
                'integer',
                Rule::exists('labels', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
