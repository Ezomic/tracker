<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');

        return $note instanceof Note
            ? ($this->user()?->can('update', $note) ?? false)
            : ($this->user()?->can('create', [Note::class, $this->route('project')]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function noteTitle(): string
    {
        return $this->string('title')->toString();
    }

    public function noteBody(): string
    {
        return $this->string('body')->toString();
    }
}
