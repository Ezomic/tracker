<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProjectLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', new Enum(ProjectLevel::class)],
            'own_issues_only' => ['sometimes', 'boolean'],
        ];
    }
}
