<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait CheckInLoggable
{
    protected ?string $logRequestId = null;

    /**
     * Get or generate a unique request ID for log correlation
     */
    protected function getLogRequestId(): string
    {
        if ($this->logRequestId === null) {
            $this->logRequestId = Str::random(8);
        }

        return $this->logRequestId;
    }

    /**
     * Log a check-in attempt with standardized format
     */
    protected function logCheckInAttempt(string $type, string $step, string $message, array $context = [], string $level = 'info'): void
    {
        $formattedMessage = sprintf(
            '[%s][%s][%s] %s',
            $type,
            $this->getLogRequestId(),
            $step,
            $message
        );

        Log::{$level}($formattedMessage, $this->sanitizeContext($context));
    }

    /**
     * Log QR validation steps
     */
    protected function logValidation(string $type, string $message, array $context = [], bool $success = true): void
    {
        $this->logCheckInAttempt(
            $type,
            'VALIDATION',
            $message,
            $context,
            $success ? 'info' : 'warning'
        );
    }

    /**
     * Log authorization and permission checks
     */
    protected function logAuthorization(string $type, string $message, array $context = [], bool $granted = true): void
    {
        $this->logCheckInAttempt(
            $type,
            'AUTH',
            $message,
            $context,
            $granted ? 'info' : 'warning'
        );
    }

    /**
     * Log business logic decisions
     */
    protected function logBusinessLogic(string $type, string $message, array $context = []): void
    {
        $this->logCheckInAttempt($type, 'BUSINESS_LOGIC', $message, $context);
    }

    /**
     * Log database operations
     */
    protected function logDatabaseOperation(string $type, string $operation, array $context = [], bool $success = true): void
    {
        $this->logCheckInAttempt(
            $type,
            'DB',
            $operation,
            $context,
            $success ? 'info' : 'error'
        );
    }

    /**
     * Log errors with full context
     */
    protected function logCheckInError(string $type, string $message, \Throwable $exception, array $context = []): void
    {
        $this->logCheckInAttempt(
            $type,
            'ERROR',
            $message,
            array_merge($context, [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]),
            'error'
        );
    }

    /**
     * Log method entry
     */
    protected function logMethodEntry(string $type, string $method, array $parameters = []): void
    {
        $this->logCheckInAttempt(
            $type,
            'ENTRY',
            "Method {$method} called",
            ['parameters' => $this->sanitizeContext($parameters)]
        );
    }

    /**
     * Log method exit
     */
    protected function logMethodExit(string $type, string $method, mixed $result = null): void
    {
        $context = [];
        if ($result !== null) {
            if (is_object($result)) {
                $context['result_type'] = get_class($result);
                if (method_exists($result, 'toArray')) {
                    $context['result'] = $this->sanitizeContext($result->toArray());
                }
            } elseif (is_array($result)) {
                $context['result'] = $this->sanitizeContext($result);
            } else {
                $context['result'] = $result;
            }
        }

        $this->logCheckInAttempt($type, 'EXIT', "Method {$method} completed", $context);
    }

    /**
     * Sanitize context to remove sensitive data
     */
    protected function sanitizeContext(array $context): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key', 'authorization'];

        array_walk_recursive($context, function (&$value, $key) use ($sensitive) {
            if (is_string($key) && in_array(strtolower($key), $sensitive)) {
                $value = '***REDACTED***';
            }
        });

        return $context;
    }

    /**
     * Format user context for logging
     */
    protected function formatUserContext(?\Illuminate\Contracts\Auth\Authenticatable $user): array
    {
        if (!$user) {
            return ['user' => null];
        }

        return [
            'user_id' => $user->id,
            'email' => $user->email ?? null,
            'name' => $user->name ?? null,
        ];
    }

    /**
     * Format model context for logging
     */
    protected function formatModelContext(?\Illuminate\Database\Eloquent\Model $model, string $prefix = 'model'): array
    {
        if (!$model) {
            return [$prefix => null];
        }

        return [
            "{$prefix}_type" => get_class($model),
            "{$prefix}_id" => $model->getKey(),
            "{$prefix}_attributes" => $this->sanitizeContext($model->getAttributes()),
        ];
    }
}
