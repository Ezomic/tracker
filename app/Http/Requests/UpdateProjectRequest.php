<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\NormalizesGithubRepos;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    use NormalizesGithubRepos;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'github_repos' => ['nullable', 'array'],
            'github_repos.*' => ['string', 'max:255'],
            'production_url' => ['nullable', 'url', 'max:255'],
            'archive_after_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'key' => $project->hasIssues()
                ? ['prohibited']
                : ['required', 'string', 'regex:/^[A-Z]{2,10}$/', 'unique:projects,key,'.$project->id],
        ];
    }
}
