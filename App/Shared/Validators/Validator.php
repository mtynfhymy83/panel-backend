<?php

declare(strict_types=1);

namespace App\Shared\Validators;

use App\Shared\Exceptions\ValidationException;

class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = $message ?? "{$field} is required.";
        }
        return $this;
    }

    public function string(string $field, int $max = 255, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        if (!is_string($value) && !is_numeric($value)) {
            $this->errors[$field] = $message ?? "{$field} must be a string.";
            return $this;
        }
        if (mb_strlen((string) $value) > $max) {
            $this->errors[$field] = $message ?? "{$field} must be at most {$max} characters.";
        }
        return $this;
    }

    public function iranPhone(string $field = 'phone', ?string $message = null): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value === '') {
            return $this;
        }
        if (!preg_match('/^09\d{9}$/', $value)) {
            $this->errors[$field] = $message ?? 'Phone must be a valid Iranian mobile number (09XXXXXXXXX).';
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this;
        }
        if (!in_array((string) $value, $allowed, true)) {
            $this->errors[$field] = $message ?? "{$field} is invalid.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $message = null): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value === '') {
            return $this;
        }
        if (mb_strlen($value) < $min) {
            $this->errors[$field] = $message ?? "{$field} must be at least {$min} characters.";
        }
        return $this;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function validate(): void
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
    }
}
