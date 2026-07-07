<?php

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Issue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
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
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Issue $issue */
        $issue = $this->route('issue');

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(IssueType::class)],
            'priority' => ['required', Rule::enum(IssuePriority::class)],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('issues', 'id')->where('parent_id', null),
                function (string $attribute, mixed $value, Closure $fail) use ($issue): void {
                    if ((int) $value === $issue->id) {
                        $fail('An issue cannot be its own epic.');
                    }

                    if ($issue->children()->exists()) {
                        $fail('An issue with sub-issues cannot itself be assigned to an epic.');
                    }
                },
            ],
        ];
    }
}
