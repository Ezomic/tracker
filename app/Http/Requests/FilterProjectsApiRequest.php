<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterProjectsApiRequest extends FormRequest
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
            'archived' => ['sometimes', 'string', Rule::in(['exclude', 'include', 'only'])],
        ];
    }

    /**
     * Archived projects stay out unless asked for, so no existing caller
     * changes behaviour. Matches the issue index.
     */
    public function archived(): string
    {
        return $this->string('archived')->toString() ?: 'exclude';
    }
}
