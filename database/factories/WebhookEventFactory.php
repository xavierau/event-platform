<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
        ];

        $eventType = $this->faker->randomElement($eventTypes);
        $stripeCreatedAt = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'stripe_event_id' => 'evt_' . $this->faker->unique()->lexify('??????????'),
            'event_type' => $eventType,
            'stripe_created_at' => $stripeCreatedAt,
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed', 'ignored']),
            'processed_at' => $this->faker->optional(0.7)->dateTimeBetween($stripeCreatedAt, 'now'),
            'retry_count' => $this->faker->numberBetween(0, 3),
            'payload' => [
                'id' => 'evt_' . $this->faker->lexify('??????????'),
                'type' => $eventType,
                'created' => $stripeCreatedAt->getTimestamp(),
                'data' => [
                    'object' => [
                        'id' => $this->generateObjectId($eventType),
                        'object' => $this->getObjectType($eventType),
                    ]
                ]
            ],
            'metadata' => $this->faker->optional(0.3)->randomElements([
                'processing_notes' => 'Test processing note',
                'retry_reason' => 'Temporary failure',
                'user_id' => $this->faker->numberBetween(1, 100),
            ], $this->faker->numberBetween(1, 3)),
            'error_message' => $this->faker->optional(0.2)->sentence(),
            'error_trace' => $this->faker->optional(0.2)->text(500),
            'processing_time_ms' => $this->faker->optional(0.6)->numberBetween(50, 5000),
            'processed_by' => $this->faker->optional(0.6)->randomElement([
                'PaymentController::handleWebhook',
                'RetryWebhookJob',
                'ManualProcessor',
            ]),
        ];
    }

    /**
     * Generate appropriate object ID based on event type.
     */
    private function generateObjectId(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'checkout.session.') => 'cs_' . $this->faker->lexify('??????????'),
            str_starts_with($eventType, 'customer.subscription.') => 'sub_' . $this->faker->lexify('??????????'),
            str_starts_with($eventType, 'invoice.') => 'in_' . $this->faker->lexify('??????????'),
            str_starts_with($eventType, 'payment_intent.') => 'pi_' . $this->faker->lexify('??????????'),
            default => 'obj_' . $this->faker->lexify('??????????'),
        };
    }

    /**
     * Get Stripe object type based on event type.
     */
    private function getObjectType(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'checkout.session.') => 'checkout.session',
            str_starts_with($eventType, 'customer.subscription.') => 'subscription',
            str_starts_with($eventType, 'invoice.') => 'invoice',
            str_starts_with($eventType, 'payment_intent.') => 'payment_intent',
            default => 'object',
        };
    }

    /**
     * State for pending events.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_at' => null,
            'retry_count' => 0,
            'error_message' => null,
            'error_trace' => null,
            'processing_time_ms' => null,
            'processed_by' => null,
        ]);
    }

    /**
     * State for completed events.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processed_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'retry_count' => $this->faker->numberBetween(0, 1),
            'error_message' => null,
            'error_trace' => null,
            'processing_time_ms' => $this->faker->numberBetween(100, 2000),
            'processed_by' => 'PaymentController::handleWebhook',
        ]);
    }

    /**
     * State for failed events.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processed_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'retry_count' => $this->faker->numberBetween(1, 5),
            'error_message' => $this->faker->sentence(),
            'error_trace' => $this->faker->text(500),
            'processing_time_ms' => $this->faker->numberBetween(50, 1000),
            'processed_by' => 'PaymentController::handleWebhook',
        ]);
    }

    /**
     * State for subscription events.
     */
    public function subscriptionEvent(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $this->faker->randomElement([
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'invoice.payment_succeeded',
                'invoice.payment_failed',
            ]),
        ]);
    }

    /**
     * State for checkout events.
     */
    public function checkoutEvent(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'checkout.session.completed',
        ]);
    }
}