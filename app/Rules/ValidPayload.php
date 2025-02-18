<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPayload implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if ($attribute !== 'payload')
        {
            $fail("You are using this rule on wrong attribute.");
            return;
        }

        if (!is_array($value)) {
            $fail("The payload isn't a valid JSON object.");
            return;
        }

        if (!isset($value['title']) || !is_string($value['title']) || trim($value['title']) === '') {
            $fail("The payload must contain non-empty property 'title'.");
            return;
        }

        foreach (['vehicle', 'meta', 'description'] as $field) {
            if (isset($value[$field]) && !is_string($value[$field])) {
                $fail("If you set '$field' in the payload, then it must be a string.");
            }
        }

    }
}