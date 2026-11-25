<?php

declare(strict_types=1);

namespace Core\Http;

use InvalidArgumentException;

final class Validator
{
    /**
     * Rules: required, nullable, string, integer, boolean, array, email, min:N, max:N.
     * Numeric strings and true/false query values are converted only when typed.
     */
    public function validate(array $input, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $rulesForField = explode('|', $ruleSet);
            if (!array_key_exists($field, $input)) {
                if (in_array('required', $rulesForField, true)) {
                    $errors[$field][] = 'required';
                }
                continue;
            }

            $value = $input[$field];
            if ($value === null && in_array('nullable', $rulesForField, true)) {
                $validated[$field] = null;
                continue;
            }

            foreach ($rulesForField as $rule) {
                if (!$this->passes($rule, $value)) {
                    $errors[$field][] = $rule;
                    break;
                }
            }
            $validated[$field] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return $validated;
    }

    private function passes(string $rule, mixed &$value): bool
    {
        [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);
        return match ($name) {
            'required' => $value !== null && $value !== '' && $value !== [],
            'nullable' => true,
            'string' => is_string($value) && preg_match('//u', $value) === 1,
            'integer' => $this->integer($value),
            'boolean' => $this->boolean($value),
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'min', 'max' => $this->bounds($name, $argument, $value),
            default => throw new InvalidArgumentException("Unknown validation rule: {$name}"),
        };
    }

    private function integer(mixed &$value): bool
    {
        if (!is_int($value) && !is_string($value)) {
            return false;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false) {
            return false;
        }
        $value = $parsed;
        return true;
    }

    private function boolean(mixed &$value): bool
    {
        if (is_bool($value)) {
            return true;
        }
        $parsed = match ($value) {
            1, '1', 'true' => true,
            0, '0', 'false' => false,
            default => null,
        };
        if ($parsed === null) {
            return false;
        }
        $value = $parsed;
        return true;
    }

    private function bounds(string $name, ?string $argument, mixed $value): bool
    {
        $limit = filter_var($argument, FILTER_VALIDATE_INT);
        if ($limit === false) {
            throw new InvalidArgumentException("Rule {$name} requires an integer limit");
        }
        $size = match (true) {
            is_string($value) => preg_match_all('/./us', $value),
            is_array($value) => count($value),
            is_int($value), is_float($value) => $value,
            default => false,
        };
        return $size !== false && ($name === 'min' ? $size >= $limit : $size <= $limit);
    }
}
