<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProjectLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'level' => ['required', Rule::in([
                ProjectLevel::Admin->value,
                ProjectLevel::Write->value,
                ProjectLevel::Read->value,
            ])],
        ];
    }
}
