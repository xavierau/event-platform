<?php

namespace App\Modules\TicketHold\Actions\Purchases;

use App\Models\TicketDefinition;
use App\Modules\TicketHold\Enums\PricingModeEnum;
use App\Modules\TicketHold\Models\HoldTicketAllocation;
use App\Modules\TicketHold\Models\PurchaseLink;

class CalculateHoldPriceAction
{
    /**
     * Calculate the effective price for a ticket through a hold allocation.
     *
     * @param  int|null  $originalPriceOverride  Override original price (from pivot if available)
     * @return array{unit_price: int, original_price: int, savings: int, savings_percentage: float, is_free: bool, pricing_mode: PricingModeEnum}
     */
    public function execute(HoldTicketAllocation $allocation, ?int $originalPriceOverride = null): array
    {
        $ticketDefinition = $allocation->ticketDefinition;
        $originalPrice = $originalPriceOverride ?? $ticketDefinition->price;

        $unitPrice = $this->calculatePrice(
            $allocation->pricing_mode,
            $originalPrice,
            $allocation->custom_price,
            $allocation->discount_percentage
        );

        $savings = max(0, $originalPrice - $unitPrice);
        $savingsPercentage = $originalPrice > 0 ? round(($savings / $originalPrice) * 100, 2) : 0;

        return [
            'unit_price' => $unitPrice,
            'original_price' => $originalPrice,
            'savings' => $savings,
            'savings_percentage' => $savingsPercentage,
            'is_free' => $unitPrice === 0,
            'pricing_mode' => $allocation->pricing_mode,
        ];
    }

    /**
     * Calculate price for a ticket definition through a purchase link.
     *
     * @return array{unit_price: int, original_price: int, savings: int, pricing_mode: PricingModeEnum}|null
     */
    public function executeForLink(
        PurchaseLink $link,
        TicketDefinition $ticketDefinition,
        ?int $originalPriceOverride = null
    ): ?array {
        // Find the allocation for this ticket definition in the hold
        $allocation = $link->ticketHold->allocations
            ->where('ticket_definition_id', $ticketDefinition->id)
            ->first();

        if (! $allocation) {
            return null;
        }

        return $this->execute($allocation, $originalPriceOverride);
    }

    /**
     * Calculate price based on pricing mode.
     *
     * @param  int  $originalPrice  In cents
     * @param  int|null  $customPrice  In cents
     * @param  int|null  $discountPercentage  0-100
     * @return int Price in cents
     */
    private function calculatePrice(
        PricingModeEnum $pricingMode,
        int $originalPrice,
        ?int $customPrice,
        ?int $discountPercentage
    ): int {
        return match ($pricingMode) {
            PricingModeEnum::ORIGINAL => $originalPrice,
            PricingModeEnum::FIXED => $customPrice ?? $originalPrice,
            PricingModeEnum::PERCENTAGE_DISCOUNT => $this->applyPercentageDiscount(
                $originalPrice,
                $discountPercentage ?? 0
            ),
            PricingModeEnum::FREE => 0,
        };
    }

    /**
     * Apply percentage discount to a price.
     *
     * @param  int  $originalPrice  In cents
     * @param  int  $percentage  0-100
     * @return int Price in cents
     */
    private function applyPercentageDiscount(int $originalPrice, int $percentage): int
    {
        $percentage = max(0, min(100, $percentage)); // Clamp between 0-100
        $discountMultiplier = (100 - $percentage) / 100;

        return (int) round($originalPrice * $discountMultiplier);
    }

    /**
     * Calculate total price for multiple items.
     *
     * @param  array<array{ticket_definition_id: int, quantity: int}>  $items
     * @return array{items: array, subtotal: int, total_savings: int}
     */
    public function calculateOrderTotal(PurchaseLink $link, array $items): array
    {
        $calculatedItems = [];
        $subtotal = 0;
        $totalSavings = 0;

        foreach ($items as $item) {
            $ticketDefinition = TicketDefinition::find($item['ticket_definition_id']);
            if (! $ticketDefinition) {
                continue;
            }

            $priceInfo = $this->executeForLink($link, $ticketDefinition);
            if (! $priceInfo) {
                continue;
            }

            $quantity = $item['quantity'];
            $lineTotal = $priceInfo['unit_price'] * $quantity;
            $lineSavings = $priceInfo['savings'] * $quantity;

            $calculatedItems[] = [
                'ticket_definition_id' => $item['ticket_definition_id'],
                'ticket_name' => $ticketDefinition->getTranslation('name', app()->getLocale()),
                'quantity' => $quantity,
                'unit_price' => $priceInfo['unit_price'],
                'original_price' => $priceInfo['original_price'],
                'line_total' => $lineTotal,
                'line_savings' => $lineSavings,
                'pricing_mode' => $priceInfo['pricing_mode']->value,
            ];

            $subtotal += $lineTotal;
            $totalSavings += $lineSavings;
        }

        return [
            'items' => $calculatedItems,
            'subtotal' => $subtotal,
            'total_savings' => $totalSavings,
        ];
    }
}
