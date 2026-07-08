<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\LabelColor;
use App\Models\Label;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
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
        /** @var Label $label */
        $label = $this->route('label');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('labels', 'name')->ignore($label->id)],
            'color' => ['required', Rule::enum(LabelColor::class)],
        ];
    }
}
