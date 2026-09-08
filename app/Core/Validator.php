<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function required(array $input, array $labels): array
    {
        $errors = [];
        foreach ($labels as $field => $label) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[$field] = $label . ' is required.';
            }
        }
        return $errors;
    }

    public static function email(?string $email, string $field = 'email'): array
    {
        return $email !== null && trim($email) !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)
            ? [$field => 'Enter a valid email address.'] : [];
    }

    public static function date(?string $date, string $field, string $label): array
    {
        if ($date === null || $date === '') return [];
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return !$parsed || $parsed->format('Y-m-d') !== $date ? [$field => $label . ' must be a valid date.'] : [];
    }

    public static function phone(?string $phone, string $field = 'phone'): array
    {
        if ($phone === null || trim($phone) === '') return [];
        return preg_match('/^\d{11}$/', trim($phone))
            ? [] : [$field => 'Phone number must contain exactly 11 digits.'];
    }

    public static function governmentId(?string $value, string $field, string $label): array
    {
        if ($value === null || trim($value) === '') return [];
        return preg_match('/^[0-9 -]{1,30}$/', trim($value))
            ? [] : [$field => $label . ' may contain only numbers, spaces, and hyphens (maximum 30 characters).'];
    }
}
