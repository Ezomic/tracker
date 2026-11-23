<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DraftIssueRequest extends FormRequest
{
    public const int TEXT_MAX = 10000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = Project::query()->whereKey($this->input('project_id'))->value('organization_id');
        $organizationId = is_int($organizationId) ? $organizationId : null;

        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('issue_templates', 'id')->where('organization_id', $organizationId),
            ],
            'text' => ['required', 'string', 'max:'.self::TEXT_MAX],
        ];
    }
}
