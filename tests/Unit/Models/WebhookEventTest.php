<?php

namespace Tests\Unit\Models;

use App\Models\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_webhook_event_from_stripe_event(): void
    {
        // Arrange
        $stripeEvent = (object) [
            'id' => 'evt_test123',
            'type' => 'customer.subscription.created',
            'created' => now()->timestamp,
            'data' => (object) [
                'object' => (object) [
                    'id' => 'sub_test123'
                ]
            ]
        ];

        // Act
        $webhookEvent = WebhookEvent::createFromStripeEvent($stripeEvent);

        // Assert
        $this->assertEquals('evt_test123', $webhookEvent->stripe_event_id);
        $this->assertEquals('customer.subscription.created', $webhookEvent->event_type);
        $this->assertEquals('pending', $webhookEvent->status);
        $this->assertNotNull($webhookEvent->payload);
        $this->assertInstanceOf(Carbon::class, $webhookEvent->stripe_created_at);
    }

    public function test_prevents_duplicate_webhook_events(): void
    {
        // Arrange
        $stripeEvent = (object) [
            'id' => 'evt_duplicate123',
            'type' => 'invoice.payment_succeeded',
            'created' => now()->timestamp,
        ];

        // Act - Create same event twice
        $first = WebhookEvent::createFromStripeEvent($stripeEvent);
        $second = WebhookEvent::createFromStripeEvent($stripeEvent);

        // Assert - Should return same record
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, WebhookEvent::where('stripe_event_id', 'evt_duplicate123')->count());
    }

    public function test_marks_event_as_processing(): void
    {
        // Arrange
        $webhookEvent = WebhookEvent::factory()->create(['status' => 'pending']);

        // Act
        $webhookEvent->markAsProcessing();

        // Assert
        $this->assertEquals('processing', $webhookEvent->status);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_marks_event_as_completed_with_metrics(): void
    {
        // Arrange
        $webhookEvent = WebhookEvent::factory()->create(['status' => 'processing']);

        // Act
        $webhookEvent->markAsCompleted(250, 'TestHandler');

        // Assert
        $this->assertEquals('completed', $webhookEvent->status);
        $this->assertEquals(250, $webhookEvent->processing_time_ms);
        $this->assertEquals('TestHandler', $webhookEvent->processed_by);
        $this->assertNull($webhookEvent->error_message);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_marks_event_as_failed_with_exception(): void
    {
        // Arrange
        $webhookEvent = WebhookEvent::factory()->create(['status' => 'processing', 'retry_count' => 1]);
        $exception = new \Exception('Test error message');

        // Act
        $webhookEvent->markAsFailed($exception, 'TestHandler');

        // Assert
        $this->assertEquals('failed', $webhookEvent->status);
        $this->assertEquals(2, $webhookEvent->retry_count);
        $this->assertEquals('Test error message', $webhookEvent->error_message);
        $this->assertEquals('TestHandler', $webhookEvent->processed_by);
        $this->assertNotNull($webhookEvent->error_trace);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_marks_event_as_ignored_with_reason(): void
    {
        // Arrange
        $webhookEvent = WebhookEvent::factory()->create(['status' => 'pending']);

        // Act
        $webhookEvent->markAsIgnored('Unsupported event type', 'TestHandler');

        // Assert
        $this->assertEquals('ignored', $webhookEvent->status);
        $this->assertEquals('TestHandler', $webhookEvent->processed_by);
        $this->assertEquals('Unsupported event type', $webhookEvent->metadata['ignored_reason']);
        $this->assertNotNull($webhookEvent->processed_at);
    }

    public function test_adds_metadata_to_event(): void
    {
        // Arrange
        $webhookEvent = WebhookEvent::factory()->create([
            'metadata' => ['existing' => 'data']
        ]);

        // Act
        $webhookEvent->addMetadata(['new' => 'info', 'count' => 42]);

        // Assert
        $this->assertEquals([
            'existing' => 'data',
            'new' => 'info', 
            'count' => 42
        ], $webhookEvent->metadata);
    }

    public function test_is_processed_returns_correct_status(): void
    {
        // Arrange & Act & Assert
        $pending = WebhookEvent::factory()->create(['status' => 'pending']);
        $completed = WebhookEvent::factory()->create(['status' => 'completed']);
        $failed = WebhookEvent::factory()->create(['status' => 'failed']);

        $this->assertFalse($pending->isProcessed());
        $this->assertTrue($completed->isProcessed());
        $this->assertFalse($failed->isProcessed());
    }

    public function test_should_retry_logic(): void
    {
        // Arrange & Act & Assert
        $failed1 = WebhookEvent::factory()->create(['status' => 'failed', 'retry_count' => 1]);
        $failed3 = WebhookEvent::factory()->create(['status' => 'failed', 'retry_count' => 3]);
        $completed = WebhookEvent::factory()->create(['status' => 'completed', 'retry_count' => 1]);

        $this->assertTrue($failed1->shouldRetry(3));
        $this->assertFalse($failed3->shouldRetry(3));
        $this->assertFalse($completed->shouldRetry(3));
    }

    public function test_event_type_detection_methods(): void
    {
        // Arrange
        $checkoutEvent = WebhookEvent::factory()->create(['event_type' => 'checkout.session.completed']);
        $subscriptionEvent = WebhookEvent::factory()->create(['event_type' => 'customer.subscription.created']);
        $invoiceEvent = WebhookEvent::factory()->create(['event_type' => 'invoice.payment_succeeded']);
        $paymentEvent = WebhookEvent::factory()->create(['event_type' => 'payment_intent.succeeded']);

        // Act & Assert
        $this->assertTrue($checkoutEvent->isCheckoutEvent());
        $this->assertFalse($checkoutEvent->isSubscriptionEvent());
        $this->assertFalse($checkoutEvent->isPaymentEvent());

        $this->assertTrue($subscriptionEvent->isSubscriptionEvent());
        $this->assertFalse($subscriptionEvent->isCheckoutEvent());

        $this->assertTrue($invoiceEvent->isSubscriptionEvent());
        $this->assertTrue($paymentEvent->isPaymentEvent());
    }

    public function test_processing_duration_attribute(): void
    {
        // Arrange & Act & Assert
        $fast = WebhookEvent::factory()->create(['processing_time_ms' => 150]);
        $slow = WebhookEvent::factory()->create(['processing_time_ms' => 2500]);
        $null = WebhookEvent::factory()->create(['processing_time_ms' => null]);

        $this->assertEquals('150ms', $fast->processing_duration);
        $this->assertEquals('2.5s', $slow->processing_duration);
        $this->assertNull($null->processing_duration);
    }

    public function test_scopes_work_correctly(): void
    {
        // Clear any existing events
        WebhookEvent::truncate();
        
        // Arrange - Create specific events for testing scopes
        WebhookEvent::factory()->pending()->create(['event_type' => 'test.pending']);
        WebhookEvent::factory()->completed()->create(['event_type' => 'test.completed']);
        WebhookEvent::factory()->failed()->create(['retry_count' => 1, 'event_type' => 'test.retryable']);
        WebhookEvent::factory()->failed()->create(['retry_count' => 5, 'event_type' => 'test.max_retry']);
        WebhookEvent::factory()->completed()->create(['event_type' => 'checkout.session.completed']);
        WebhookEvent::factory()->completed()->create(['created_at' => now()->subDays(2), 'event_type' => 'test.old']);

        // Act & Assert
        $this->assertEquals(1, WebhookEvent::pending()->count());
        $this->assertEquals(1, WebhookEvent::retryable(3)->count());
        $this->assertEquals(1, WebhookEvent::ofType('checkout.session.completed')->count());
        $this->assertEquals(5, WebhookEvent::recent(24)->count()); // All except the 2-day old one
    }
}