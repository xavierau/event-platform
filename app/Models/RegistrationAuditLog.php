<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class RegistrationAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'step',
        'action',
        'status',
        'message',
        'request_data',
        'response_data',
        'metadata',
        'user_id',
        'email',
        'selected_plan',
        'error_message',
        'stripe_session_id',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Generate a new flow ID for tracking a complete registration flow.
     */
    public static function generateFlowId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Get the user associated with this log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new log entry with sanitized data.
     */
    public static function logStep(array $data): self
    {
        // Sanitize request data
        if (isset($data['request_data'])) {
            $data['request_data'] = self::sanitizeData($data['request_data']);
        }

        // Sanitize response data
        if (isset($data['response_data'])) {
            $data['response_data'] = self::sanitizeData($data['response_data']);
        }

        return self::create($data);
    }

    /**
     * Sanitize data by removing sensitive information.
     */
    private static function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password', 
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'card_number',
            'cvv',
            'cvc',
        ];

        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeData($value);
            } elseif (in_array(strtolower($key), $sensitiveKeys) || 
                     str_contains(strtolower($key), 'password') ||
                     str_contains(strtolower($key), 'secret')) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Scope to get logs for a specific registration flow.
     */
    public function scopeByFlow($query, string $flowId)
    {
        return $query->where('flow_id', $flowId)->orderBy('created_at');
    }

    /**
     * Scope to get logs for a specific step.
     */
    public function scopeByStep($query, string $step)
    {
        return $query->where('step', $step);
    }

    /**
     * Scope to get failed registration attempts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get successful registration steps.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get recent logs within specified hours.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to get logs for a specific email address.
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
