<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Duration;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DurationRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minutes = is_string($value) ? Duration::toMinutes($value) : null;

        if ($minutes === null || $minutes < 1) {
            $fail('Enter a duration like "1h 30m", "45m" or "2h".');
        }
    }
}
