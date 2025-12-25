<?php

namespace App\Modules\TicketHold\Actions\Purchases;

use App\Enums\BookingStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\TicketHold\DTOs\HoldPurchaseRequestData;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Exceptions\InsufficientHoldInventoryException;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Exceptions\UserNotAuthorizedForLinkException;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\PurchaseLinkPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessHoldPurchaseAction
{
    public function __construct(
        private CalculateHoldPriceAction $calculatePrice
    ) {}

    /**
     * Process a purchase through a hold link.
     *
     * @return array{transaction: Transaction, bookings: array<Booking>, purchases: array<PurchaseLinkPurchase>, totals: array}
     *
     * @throws LinkNotUsableException
     * @throws HoldNotActiveException
     * @throws InsufficientHoldInventoryException
     * @throws UserNotAuthorizedForLinkException
     */
    public function execute(
        HoldPurchaseRequestData $requestData,
        ?User $user = null,
        ?PurchaseLinkAccess $access = null
    ): array {
        return DB::transaction(function () use ($requestData, $user, $access) {
            // Find and lock the purchase link to prevent concurrent purchases
            $link = PurchaseLink::where('code', $requestData->link_code)
                ->lockForUpdate()
                ->firstOrFail();

            // Load relationships after locking
            $link->load(['ticketHold.allocations.ticketDefinition']);

            // Lock the allocations to prevent race conditions during purchase
            $allocationIds = $link->ticketHold->allocations->pluck('id')->toArray();
            if (! empty($allocationIds)) {
                \App\Modules\TicketHold\Models\HoldTicketAllocation::whereIn('id', $allocationIds)
                    ->lockForUpdate()
                    ->get();

                // Refresh allocations to get locked state
                $link->ticketHold->load('allocations.ticketDefinition');
            }

            $this->validateLink($link, $user);
            $this->validateItems($link, $requestData->items);

            // Calculate totals
            $items = array_map(fn ($item) => [
                'ticket_definition_id' => $item->ticket_definition_id,
                'quantity' => $item->quantity,
            ], $requestData->items);

            $totals = $this->calculatePrice->calculateOrderTotal($link, $items);

            // Create transaction
            $transaction = $this->createTransaction($user, $totals);

            // Create bookings and purchase records
            $bookings = [];
            $purchases = [];

            foreach ($requestData->items as $item) {
                $allocation = $link->ticketHold->allocations
                    ->where('ticket_definition_id', $item->ticket_definition_id)
                    ->first();

                $priceInfo = $this->calculatePrice->execute($allocation);

                // Create bookings (one per quantity)
                for ($i = 0; $i < $item->quantity; $i++) {
                    $booking = $this->createBooking(
                        $transaction,
                        $allocation->ticketDefinition,
                        $priceInfo['unit_price'],
                        $link->ticketHold->eventOccurrence->event_id
                    );
                    $bookings[] = $booking;

                    // Create purchase record
                    $purchase = $this->createPurchaseRecord(
                        $link,
                        $booking,
                        $transaction,
                        $user,
                        $priceInfo,
                        $access
                    );
                    $purchases[] = $purchase;
                }

                // Update allocation purchased quantity
                $allocation->recordPurchase($item->quantity);
            }

            // Update link purchased quantity
            $totalQuantity = array_sum(array_map(fn ($item) => $item->quantity, $requestData->items));
            $link->recordPurchase($totalQuantity);

            // Mark access as resulting in purchase
            if ($access) {
                $access->markAsPurchased();
            }

            return [
                'transaction' => $transaction,
                'bookings' => $bookings,
                'purchases' => $purchases,
                'totals' => $totals,
            ];
        });
    }

    /**
     * Validate the purchase link is usable.
     *
     * @throws LinkNotUsableException
     * @throws HoldNotActiveException
     * @throws UserNotAuthorizedForLinkException
     */
    private function validateLink(PurchaseLink $link, ?User $user): void
    {
        // Check if link is usable
        if (! $link->status->isUsable()) {
            throw new LinkNotUsableException(
                "Purchase link '{$link->code}' is not active. Status: {$link->status->label()}"
            );
        }

        // Check link expiration
        if ($link->is_expired) {
            throw new LinkNotUsableException(
                "Purchase link '{$link->code}' has expired."
            );
        }

        // Check hold is usable
        if (! $link->ticketHold->is_usable) {
            throw new HoldNotActiveException(
                'The ticket hold associated with this link is not active.'
            );
        }

        // Check user authorization for user-tied links
        if (! $link->canBeUsedByUser($user)) {
            throw new UserNotAuthorizedForLinkException(
                'You are not authorized to use this purchase link.'
            );
        }
    }

    /**
     * Validate items against hold allocation availability.
     *
     * @throws InsufficientHoldInventoryException
     * @throws LinkNotUsableException
     */
    private function validateItems(PurchaseLink $link, array $items): void
    {
        $totalQuantity = 0;

        foreach ($items as $item) {
            $allocation = $link->ticketHold->allocations
                ->where('ticket_definition_id', $item->ticket_definition_id)
                ->first();

            if (! $allocation) {
                throw new InsufficientHoldInventoryException(
                    "Ticket definition {$item->ticket_definition_id} is not available in this hold."
                );
            }

            if ($allocation->remaining_quantity < $item->quantity) {
                throw new InsufficientHoldInventoryException(
                    "Insufficient inventory for ticket. Requested: {$item->quantity}, Available: {$allocation->remaining_quantity}"
                );
            }

            $totalQuantity += $item->quantity;
        }

        // Check link quantity limits
        if (! $link->canPurchaseQuantity($totalQuantity)) {
            throw new LinkNotUsableException(
                "Cannot purchase {$totalQuantity} tickets through this link. ".
                ($link->remaining_quantity !== null
                    ? "Remaining quota: {$link->remaining_quantity}"
                    : 'Link quantity restrictions apply.')
            );
        }
    }

    /**
     * Create the transaction record.
     */
    private function createTransaction(?User $user, array $totals): Transaction
    {
        return Transaction::create([
            'user_id' => $user?->id,
            'transaction_number' => 'TH-'.strtoupper(Str::random(12)),
            'total_amount' => $totals['subtotal'],
            'currency' => config('cashier.currency', 'hkd'),
            'status' => TransactionStatusEnum::CONFIRMED, // Hold purchases are instant
            'metadata' => [
                'source' => 'ticket_hold',
                'total_savings' => $totals['total_savings'],
            ],
        ]);
    }

    /**
     * Create a booking record.
     */
    private function createBooking(
        Transaction $transaction,
        $ticketDefinition,
        int $unitPrice,
        int $eventId
    ): Booking {
        return Booking::create([
            'transaction_id' => $transaction->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'event_id' => $eventId,
            'booking_number' => 'BK-'.strtoupper(Str::random(10)),
            'quantity' => 1,
            'price_at_booking' => $unitPrice,
            'currency_at_booking' => config('cashier.currency', 'hkd'),
            'status' => BookingStatusEnum::CONFIRMED,
            'qr_code_identifier' => Str::uuid()->toString(),
            'max_allowed_check_ins' => 1,
            'metadata' => [
                'source' => 'ticket_hold',
            ],
        ]);
    }

    /**
     * Create a purchase link purchase record.
     */
    private function createPurchaseRecord(
        PurchaseLink $link,
        Booking $booking,
        Transaction $transaction,
        ?User $user,
        array $priceInfo,
        ?PurchaseLinkAccess $access
    ): PurchaseLinkPurchase {
        return PurchaseLinkPurchase::create([
            'purchase_link_id' => $link->id,
            'booking_id' => $booking->id,
            'transaction_id' => $transaction->id,
            'user_id' => $user?->id,
            'quantity' => 1,
            'unit_price' => $priceInfo['unit_price'],
            'original_price' => $priceInfo['original_price'],
            'currency' => config('cashier.currency', 'hkd'),
            'access_id' => $access?->id,
        ]);
    }
}
