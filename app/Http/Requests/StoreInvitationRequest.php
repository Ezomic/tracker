<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProjectRole;
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
            // Ownership is not transferable through an invitation.
            'role' => ['required', new Enum(ProjectRole::class), Rule::in([
                ProjectRole::Admin->value,
                ProjectRole::Member->value,
            ])],
        ];
    }
}
