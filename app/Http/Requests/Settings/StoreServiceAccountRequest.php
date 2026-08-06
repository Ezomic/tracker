<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // A service account with no project can do nothing, so at least one
            // is required rather than defaulting to "all".
            'projects' => ['required', 'array', 'min:1'],
            'projects.*' => ['string', 'exists:projects,key'],
        ];
    }
}
