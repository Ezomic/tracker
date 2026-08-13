<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\IssueRelation;
use App\Models\Issue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueLinkRequest extends FormRequest
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
            'relation' => ['required', Rule::in(array_map(
                fn (IssueRelation $relation): string => $relation->value,
                IssueRelation::selectable(),
            ))],
            'issue' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $source = $this->route('issue');
                    $target = Issue::query()->where('identifier', $value)->first();

                    if ($target === null) {
                        $fail('That issue does not exist.');

                        return;
                    }

                    if ($source instanceof Issue && $target->id === $source->id) {
                        $fail('An issue cannot be linked to itself.');
                    }
                },
            ],
        ];
    }
}
