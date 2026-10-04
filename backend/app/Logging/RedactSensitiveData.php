<?php

namespace App\Logging;

use Monolog\LogRecord;

class RedactSensitiveData
{
    private const SENSITIVE_KEYS = [
        'access_token',
        'account_number',
        'authorization',
        'cookie',
        'guest_access_code',
        'password',
        'password_confirmation',
        'recipient',
        'refresh_token',
        'remember_token',
        'secret',
        'signature',
        'token',
        'x-sensitive-confirmation',
        'x-sandbox-signature',
        'x-xsrf-token',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->sanitizeString($record->message),
            context: $this->sanitizeArray($record->context),
            extra: $this->sanitizeArray($record->extra),
        );
    }

    /** @param array<string|int, mixed> $values */
    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $values[$key] = $this->sanitizeString($value);
            }
        }

        return $values;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $value) ?? $value;

        return preg_replace_callback('/(?<!\d)\d{10,19}(?!\d)/', static function (array $match): string {
            return str_repeat('*', max(0, strlen($match[0]) - 4)).substr($match[0], -4);
        }, $value) ?? $value;
    }
}
