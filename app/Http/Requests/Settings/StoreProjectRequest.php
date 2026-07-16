<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\NormalizesGithubRepos;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    use NormalizesGithubRepos;

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
        return [
            'key' => ['required', 'string', 'regex:/^[A-Z]{2,10}$/', 'unique:projects,key'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'github_repos' => ['nullable', 'array'],
            'github_repos.*' => ['string', 'max:255'],
            'production_url' => ['nullable', 'url', 'max:255'],
            'archive_after_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
