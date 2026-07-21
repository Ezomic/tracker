<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\ResolvesCurrentUser;
use App\Services\CurrentOrganization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    use ResolvesCurrentUser;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->for($this->currentUser())?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
