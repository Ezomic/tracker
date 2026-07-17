<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\IssueTemplate;
use App\Models\Project;
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
        /** @var Project $project */
        $project = $this->route('project');

        /** @var IssueTemplate|null $template */
        $template = $this->route('template');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('issue_templates', 'name')
                    ->where('project_id', $project->id)
                    ->ignore($template?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', Rule::enum(IssueType::class)],
            'priority' => ['nullable', Rule::enum(IssuePriority::class)],
            'labels' => ['array'],
            'labels.*' => [
                'integer',
                Rule::exists('labels', 'id')->where('user_id', $project->ownerId()),
            ],
        ];
    }
}
