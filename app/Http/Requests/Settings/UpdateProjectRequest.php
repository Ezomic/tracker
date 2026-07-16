<?php

namespace App\Http\Requests\Settings;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
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

    protected function prepareForValidation(): void
    {
        $repos = $this->input('github_repos');

        if (is_array($repos)) {
            $this->merge([
                'github_repos' => array_values(array_filter(
                    $repos,
                    fn (mixed $repo): bool => is_string($repo) && trim($repo) !== '',
                )),
            ]);
        }
    }
}
