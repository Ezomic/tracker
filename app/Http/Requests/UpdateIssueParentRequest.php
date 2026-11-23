<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIssueParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue && ($this->user()?->can('update', $issue) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent' => ['present', 'nullable', 'string', $this->validParent(...)],
        ];
    }

    public function parentIssue(): ?Issue
    {
        $identifier = $this->string('parent')->toString();

        return $identifier === '' ? null : $this->findVisible($identifier);
    }

    private function validParent(string $attribute, mixed $value, Closure $fail): void
    {
        $issue = $this->route('issue');
        $parent = $this->findVisible(is_string($value) ? $value : '');

        // One answer for "missing" and "hidden", so the endpoint cannot be
        // used to probe for issues in projects the user cannot see.
        if ($parent === null || ! $issue instanceof Issue) {
            $fail(__('That epic does not exist.'));

            return;
        }

        if ($parent->is($issue)) {
            $fail(__('An issue cannot be its own epic.'));
        } elseif ($parent->project_id !== $issue->project_id) {
            $fail(__('The epic must belong to the same project.'));
        } elseif ($parent->parent_id !== null) {
            $fail(__('A sub-issue cannot be an epic.'));
        } elseif ($issue->children()->exists()) {
            $fail(__('An issue with sub-issues cannot itself be assigned to an epic.'));
        }
    }

    private function findVisible(string $identifier): ?Issue
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return null;
        }

        return Issue::query()->visibleTo($user)->where('identifier', $identifier)->first();
    }
}
