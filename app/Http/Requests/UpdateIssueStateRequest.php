<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueStateRequest extends FormRequest
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
        $issue = $this->route('issue');
        $projectTypeId = $issue instanceof Issue ? $issue->project->project_type_id : null;

        return [
            // The target lane must belong to this issue's project type.
            'workflow_state_id' => [
                'required',
                'integer',
                Rule::exists('workflow_states', 'id')->where('project_type_id', $projectTypeId),
            ],
        ];
    }
}
