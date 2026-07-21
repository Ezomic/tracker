<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\LabelColor;
use App\Http\Requests\Concerns\ResolvesCurrentUser;
use App\Services\CurrentOrganization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
{
    use ResolvesCurrentUser;

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
            // Unique within the current organization's labels.
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('labels', 'name')
                    ->where('organization_id', app(CurrentOrganization::class)->for($this->currentUser())?->id),
            ],
            'color' => ['required', Rule::enum(LabelColor::class)],
        ];
    }
}
