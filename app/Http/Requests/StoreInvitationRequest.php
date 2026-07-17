<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProjectLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreInvitationRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'level' => ['required', new Enum(ProjectLevel::class), Rule::in([
                ProjectLevel::Admin->value,
                ProjectLevel::Write->value,
                ProjectLevel::Read->value,
            ])],
        ];
    }
}
