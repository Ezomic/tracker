<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedViewRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:60'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'criteria' => ['array'],
            'criteria.search' => ['nullable', 'string', 'max:255'],
            'criteria.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'criteria.status' => ['nullable', Rule::enum(IssueStatus::class)],
            'criteria.type' => ['nullable', Rule::enum(IssueType::class)],
            'criteria.priority' => ['nullable', Rule::enum(IssuePriority::class)],
            'criteria.label_id' => ['nullable', 'integer', 'exists:labels,id'],
        ];
    }
}
