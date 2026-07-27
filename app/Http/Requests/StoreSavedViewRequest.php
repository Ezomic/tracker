<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Label;
use App\Models\Project;
use App\Models\User;
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
        /** @var User $user */
        $user = $this->user();

        // Only reference projects and labels the user can actually see, so a
        // saved view can't be pinned to another organization's resources.
        $projectIds = Project::query()->visibleTo($user)->pluck('id')->all();
        $labelIds = Label::query()
            ->whereHas('organization.members', fn ($query) => $query->whereKey($user->id))
            ->pluck('id')
            ->all();

        return [
            'name' => ['required', 'string', 'max:60'],
            'project_id' => ['nullable', 'integer', Rule::in($projectIds)],
            'criteria' => ['array'],
            'criteria.search' => ['nullable', 'string', 'max:255'],
            'criteria.project_id' => ['nullable', 'integer', Rule::in($projectIds)],
            'criteria.status' => ['nullable', Rule::enum(IssueStatus::class)],
            'criteria.type' => ['nullable', Rule::enum(IssueType::class)],
            'criteria.priority' => ['nullable', Rule::enum(IssuePriority::class)],
            'criteria.label_id' => ['nullable', 'integer', Rule::in($labelIds)],
        ];
    }
}
