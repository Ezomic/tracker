<?php

namespace App\Http\Requests\Settings;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
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
        /** @var Project $team */
        $team = $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => $team->hasIssues()
                ? ['prohibited']
                : ['required', 'string', 'regex:/^[A-Z]{2,10}$/', 'unique:projects,key,'.$team->id],
        ];
    }
}
