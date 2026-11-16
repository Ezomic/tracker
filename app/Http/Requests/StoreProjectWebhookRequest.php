<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Webhooks\WebhookTargetGuard;
use App\Services\Webhooks\WebhookTargetRefused;
use App\Support\Cast;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectWebhookRequest extends FormRequest
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
            // The secret only protects the payload in transit if the transport
            // is encrypted, so plain http endpoints are refused outright.
            'url' => [
                'bail',
                'required',
                'url:https',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        app(WebhookTargetGuard::class)->resolve(Cast::string($value));
                    } catch (WebhookTargetRefused $refused) {
                        $fail($refused->getMessage());
                    }
                },
            ],
        ];
    }
}
