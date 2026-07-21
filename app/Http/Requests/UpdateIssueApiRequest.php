<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Label;
use App\Models\User;
use App\Rules\DurationRule;
use App\Support\Cast;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateIssueApiRequest extends FormRequest
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

        // An empty assignee means "unassign", matching the create endpoint.
        if ($this->input('assignee') === '') {
            $this->merge(['assignee' => null]);
        }

        // Accept labels as a comma-separated string as well as an array, so
        // shell callers can pass them without building JSON.
        if (is_string($this->input('labels'))) {
            $names = array_values(array_filter(array_map('trim', explode(',', $this->input('labels')))));
            $this->merge(['labels' => $names]);
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', Rule::enum(IssueType::class)],
            'priority' => ['sometimes', Rule::enum(IssuePriority::class)],
            'estimate' => ['sometimes', 'nullable', 'string', new DurationRule],
            'assignee' => [
                'sometimes',
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($issue): void {
                    $user = User::query()->where('email', Str::lower(Cast::string($value)))->first();

                    if ($user === null || ! $issue->project->hasMember($user)) {
                        $fail('The assignee must be a member of this project.');
                    }
                },
            ],
            'labels' => ['sometimes', 'array'],
            'labels.*' => [
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($issue): void {
                    $exists = Label::query()
                        ->forProject($issue->project)
                        ->whereRaw('lower(name) = ?', [Str::lower(Cast::string($value))])
                        ->exists();

                    if (! $exists) {
                        $fail('The label ['.Cast::string($value).'] does not exist in this project.');
                    }
                },
            ],
            'parent' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('issues', 'identifier')->where('parent_id', null),
                function (string $attribute, mixed $value, Closure $fail) use ($issue): void {
                    $parent = Issue::query()->where('identifier', $value)->first();

                    if ($parent === null) {
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
