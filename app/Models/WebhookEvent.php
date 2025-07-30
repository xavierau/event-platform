<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'stripe_created_at',
        'status',
        'processed_at',
        'retry_count',
        'payload',
        'metadata',
        'error_message',
        'error_trace',
        'processing_time_ms',
        'processed_by',
    ];

    protected $casts = [
        'stripe_created_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
        'retry_count' => 'integer',
        'processing_time_ms' => 'integer',
    ];

    /**
     * Create or retrieve webhook event from Stripe event.
     */
    public static function createFromStripeEvent(object $stripeEvent): self
    {
        return self::firstOrCreate(
            ['stripe_event_id' => $stripeEvent->id],
            [
                'event_type' => $stripeEvent->type,
                'stripe_created_at' => Carbon::createFromTimestamp($stripeEvent->created),
                'payload' => (array) $stripeEvent,
                'status' => 'pending',
            ]
        );
    }

    /**
     * Check if this event has already been processed successfully.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if this event should be retried.
     */
    public function shouldRetry(int $maxRetries = 3): bool
    {
        return $this->status === 'failed' && $this->retry_count < $maxRetries;
    }

    /**
     * Mark event as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark event as completed with processing metrics.
     */
    public function markAsCompleted(int $processingTimeMs = null, string $processedBy = null): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'processing_time_ms' => $processingTimeMs,
            'processed_by' => $processedBy,
            'error_message' => null,
            'error_trace' => null,
        ]);
    }

    /**
     * Mark event as failed with error details.
     */
    public function markAsFailed(\Exception $exception, string $processedBy = null): void
    {
        $this->update([
            'status' => 'failed',
            'processed_at' => now(),
            'retry_count' => $this->retry_count + 1,
            'error_message' => $exception->getMessage(),
            'error_trace' => $exception->getTraceAsString(),
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Mark event as ignored (e.g., unsupported event type).
     */
    public function markAsIgnored(string $reason = null, string $processedBy = null): void
    {
        $metadata = $this->metadata ?? [];
        if ($reason) {
            $metadata['ignored_reason'] = $reason;
        }

        $this->update([
            'status' => 'ignored',
            'processed_at' => now(),
            'metadata' => $metadata,
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Add metadata to the event.
     */
    public function addMetadata(array $data): void
    {
        $metadata = $this->metadata ?? [];
        $this->update([
            'metadata' => array_merge($metadata, $data),
        ]);
    }

    /**
     * Scope for events that need processing.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed events that can be retried.
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->where('status', 'failed')
                    ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Scope for events by type.
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for recent events.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the Stripe event object from payload.
     */
    public function getStripeEvent(): object
    {
        return (object) $this->payload;
    }

    /**
     * Get processing duration in a human-readable format.
     */
    public function getProcessingDurationAttribute(): ?string
    {
        if (!$this->processing_time_ms) {
            return null;
        }

        if ($this->processing_time_ms < 1000) {
            return $this->processing_time_ms . 'ms';
        }

        return round($this->processing_time_ms / 1000, 2) . 's';
    }

    /**
     * Check if event is related to checkout sessions.
     */
    public function isCheckoutEvent(): bool
    {
        return str_starts_with($this->event_type, 'checkout.session.');
    }

    /**
     * Check if event is related to subscriptions.
     */
    public function isSubscriptionEvent(): bool
    {
        return str_starts_with($this->event_type, 'customer.subscription.') ||
               str_starts_with($this->event_type, 'invoice.');
    }

    /**
     * Check if event is related to payments.
     */
    public function isPaymentEvent(): bool
    {
        return str_starts_with($this->event_type, 'payment_intent.') ||
               str_starts_with($this->event_type, 'charge.');
    }
}