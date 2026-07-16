<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Issue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueParentRequest extends FormRequest
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
        /** @var Issue $issue */
        $issue = $this->route('issue');

        return [
            'parent' => [
                'present',
                'nullable',
                'string',
                Rule::exists('issues', 'identifier')->where('parent_id', null),
                function (string $attribute, mixed $value, Closure $fail) use ($issue): void {
                    $parent = Issue::query()->where('identifier', $value)->first();

                    if (! $parent) {
                        return;
                    }

                    if ($parent->id === $issue->id) {
                        $fail('An issue cannot be its own epic.');
                    }

                    if ($parent->project_id !== $issue->project_id) {
                        $fail('The parent issue must belong to the same project.');
                    }

                    if ($issue->children()->exists()) {
                        $fail('An issue with sub-issues cannot itself be assigned to an epic.');
                    }
                },
            ],
        ];
    }
}
