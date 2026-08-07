<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\WorkflowState;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Accepts either form of "move this issue": the legacy four-value `status`, or
 * a `workflow_state` naming a lane on the project's type. Exactly one is
 * required, so a caller cannot send two and leave the outcome to precedence.
 *
 * `status` is deprecated and sunsets 2026-09-30; see
 * docs/api-versioning-2026-08-06.md.
 */
class UpdateIssueStatusApiRequest extends FormRequest
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
            'status' => ['required_without:workflow_state', 'missing_with:workflow_state', Rule::enum(IssueStatus::class)],
            'workflow_state' => [
                'required_without:status',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->resolveState() === null) {
                        $fail('The selected workflow state does not exist on this project\'s type.');
                    }
                },
            ],
        ];
    }

    /**
     * A lane by id or by name, scoped to the project's type so one project
     * cannot move an issue into another type's lane.
     */
    public function resolveState(): ?WorkflowState
    {
        $issue = $this->route('issue');

        if (! $issue instanceof Issue || $issue->project->project_type_id === null) {
            return null;
        }

        $value = $this->string('workflow_state')->toString();

        if ($value === '') {
            return null;
        }

        return WorkflowState::query()
            ->where('project_type_id', $issue->project->project_type_id)
            ->where(function (Builder $query) use ($value): void {
                $query->whereRaw('lower(name) = ?', [mb_strtolower($value)]);

                if (ctype_digit($value)) {
                    $query->orWhere('id', (int) $value);
                }
            })
            ->orderBy('position')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.missing_with' => 'Send either status or workflow_state, not both.',
        ];
    }
}
