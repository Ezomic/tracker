<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Support\Cast;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateIssuesRequest extends FormRequest
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
            // Capped: a selection is something a person made on one screen, and
            // an unbounded batch is a way to lock the table inside one
            // transaction.
            'issues' => ['required', 'array', 'min:1', 'max:200'],
            'issues.*' => ['string', 'max:255'],
            'status' => ['sometimes', Rule::enum(IssueStatus::class)],
            'priority' => ['sometimes', Rule::enum(IssuePriority::class)],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'archived' => ['sometimes', 'boolean'],
            'add_labels' => ['sometimes', 'array'],
            'add_labels.*' => ['string'],
            'remove_labels' => ['sometimes', 'array'],
            'remove_labels.*' => ['string'],
        ];
    }

    /**
     * A request that names issues but no change is a mistake, not a no-op.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->changes() === []) {
                    $validator->errors()->add('status', 'Pick at least one change to apply.');
                }
            },
        ];
    }

    /**
     * Only the keys actually present, so an omitted field is left alone rather
     * than being nulled.
     *
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        $changes = [];

        foreach (['status', 'priority', 'assignee_id', 'archived', 'add_labels', 'remove_labels'] as $key) {
            if ($this->has($key)) {
                $changes[$key] = $this->input($key);
            }
        }

        return $changes;
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        return Cast::strings($this->input('issues', []));
    }
}
