<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\StatusCategory;
use App\Models\ProjectType;
use App\Services\CurrentOrganization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $user === null ? null : app(CurrentOrganization::class)->for($user);

        return $user !== null && $organization !== null && $user->can('update', $organization);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $organization = $user === null ? null : app(CurrentOrganization::class)->for($user);
        $type = $this->route('projectType');
        $typeId = $type instanceof ProjectType ? $type->id : null;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('project_types', 'name')
                    ->where('organization_id', $organization?->id)
                    ->ignore($typeId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'states' => ['required', 'array', 'min:1'],
            'states.*.id' => ['nullable', 'integer'],
            'states.*.name' => ['required', 'string', 'max:255'],
            'states.*.category' => ['required', Rule::enum(StatusCategory::class)],
            'states.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'states.*.isDefault' => ['boolean'],
        ];
    }
}
