<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\Category;
use App\Services\CurrentOrganization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        /** @var Category $category */
        $category = $this->route('category');
        $organizationId = app(CurrentOrganization::class)->for($this->user())?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('organization_id', $organizationId),
                function (string $attribute, mixed $value, Closure $fail) use ($category): void {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === $category->id || in_array((int) $value, $category->descendantIds(), true)) {
                        $fail('A category cannot be moved inside itself.');
                    }
                },
            ],
        ];
    }
}
