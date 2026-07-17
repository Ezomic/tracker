<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Enums\ProjectLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationInvitationRequest extends FormRequest
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
            'role' => ['required', Rule::in([
                OrganizationRole::Member->value,
                OrganizationRole::Guest->value,
            ])],
            'project_id' => ['nullable', 'integer'],
            'level' => ['nullable', 'required_with:project_id', Rule::in([
                ProjectLevel::Admin->value,
                ProjectLevel::Write->value,
                ProjectLevel::Read->value,
            ])],
        ];
    }
}
