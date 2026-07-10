<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssueType;
use App\Models\Issue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
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
        // `team` is the deprecated alias for `project`; accept it when `project` is absent.
        if (blank($this->input('project')) && filled($this->input('team'))) {
            $this->merge(['project' => $this->input('team')]);
        }

        if ($this->input('parent') === '') {
            $this->merge(['parent' => null]);
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
            'project' => ['required', 'string', 'exists:projects,key'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(IssueType::class)],
            'description' => ['nullable', 'string'],
            'parent' => [
                'nullable',
                'string',
                Rule::exists('issues', 'identifier')->where('parent_id', null),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $parent = Issue::query()->where('identifier', $value)->first();

                    if ($parent && strcasecmp($parent->project->key, (string) $this->input('project')) !== 0) {
                        $fail('The parent issue must belong to the same project.');
                    }
                },
            ],
        ];
    }
}
