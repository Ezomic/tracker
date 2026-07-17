<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssueType;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueWebRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }

        if ($this->input('template_id') === '') {
            $this->merge(['template_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(IssueType::class)],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('issues', 'id')->where('parent_id', null),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('issue_templates', 'id')->where(
                    'organization_id',
                    Project::query()->whereKey($this->input('project_id'))->value('organization_id'),
                ),
            ],
        ];
    }
}
