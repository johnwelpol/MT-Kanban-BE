<?php

namespace App\Rules;

use App\Support\WorkspaceSlug;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class AvailableWorkspaceSlug implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reason = is_string($value) ? WorkspaceSlug::check($value) : 'invalid';

        match ($reason) {
            'invalid' => $fail('The workspace URL must be 3-40 lowercase letters, numbers or hyphens, and cannot start or end with a hyphen.'),
            'reserved' => $fail('This URL is reserved.'),
            'taken' => $fail('This URL is already taken.'),
            null => null,
        };
    }
}
