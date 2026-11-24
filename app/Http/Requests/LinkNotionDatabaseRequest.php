<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Project;
use App\Support\NotionDatabaseId;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkNotionDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && ($this->user()?->can('manageNotion', $project) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'database' => [
                'bail',
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (NotionDatabaseId::normalise($value) === null) {
                        $fail(__('Enter the link to the Notion database, or its 32-character ID.'));
                    }
                },
            ],
        ];
    }

    public function databaseId(): string
    {
        return (string) NotionDatabaseId::normalise($this->input('database'));
    }
}
