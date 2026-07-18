<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\User;
use App\Rules\DurationRule;
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

        if ($this->input('assignee_id') === '') {
            $this->merge(['assignee_id' => null]);
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
            'estimate' => ['nullable', 'string', new DurationRule],
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
            'assignee_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($issue): void {
                    $user = User::query()->whereKey((int) $value)->first();

                    if ($user === null || ! $issue->project->hasMember($user)) {
                        $fail('The assignee must be a member of this project.');
                    }
                },
            ],
            'labels' => ['array'],
            'labels.*' => [
                'integer',
                // Only the project's own label set — not any id in the table.
                Rule::exists('labels', 'id')->where('organization_id', $issue->project->organization_id),
            ],
        ];
    }
}
