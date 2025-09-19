<?php

namespace App\Services;

use App\Actions\TicketDefinition\UpsertTicketDefinitionAction;
use App\DataTransferObjects\TicketDefinitionData;
use App\Models\TicketDefinition;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class TicketDefinitionService
{
    protected UpsertTicketDefinitionAction $upsertTicketDefinitionAction;

    public function __construct(UpsertTicketDefinitionAction $upsertTicketDefinitionAction)
    {
        $this->upsertTicketDefinitionAction = $upsertTicketDefinitionAction;
    }

    public function getAllTicketDefinitions(array $filters = [], int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        // TODO: Implement filtering based on $filters if needed
        return TicketDefinition::query()
            ->with($with)
            ->latest()
            ->paginate($perPage);
    }

    public function findTicketDefinitionById(int $id, array $with = []): ?TicketDefinition
    {
        return TicketDefinition::with($with)->find($id);
    }

    public function createTicketDefinition(TicketDefinitionData $ticketDefinitionData): TicketDefinition
    {
        Log::info('TicketDefinitionService: Creating ticket definition via action', $ticketDefinitionData->toArray());
        return $this->upsertTicketDefinitionAction->execute($ticketDefinitionData);
    }

    public function updateTicketDefinition(int $ticketDefinitionId, TicketDefinitionData $ticketDefinitionData): TicketDefinition
    {
        Log::info('TicketDefinitionService: Updating ticket definition ' . $ticketDefinitionId . ' via action', $ticketDefinitionData->toArray());
        return $this->upsertTicketDefinitionAction->execute($ticketDefinitionData, $ticketDefinitionId);
    }

    public function deleteTicketDefinition(int $ticketDefinitionId): void
    {
        $ticketDefinition = TicketDefinition::findOrFail($ticketDefinitionId);
        // Add any related cleanup logic if necessary before deleting
        $ticketDefinition->delete();
        Log::info('TicketDefinitionService: Deleted ticket definition ' . $ticketDefinitionId);
    }

    /**
     * Calculate the membership price for a ticket definition and user.
     */
    public function calculateMembershipPrice(TicketDefinition $ticketDefinition, User $user): int
    {
        return $ticketDefinition->getMembershipPrice($user);
    }

    /**
     * Apply a membership discount to a base price.
     */
    public function applyMembershipDiscount(int $basePrice, string $discountType, int $discountValue): int
    {
        $discountedPrice = $basePrice;

        if ($discountType === 'percentage') {
            $discountAmount = round($basePrice * ($discountValue / 100));
            $discountedPrice = max(0, $basePrice - $discountAmount);
        } elseif ($discountType === 'fixed') {
            $discountedPrice = max(0, $basePrice - $discountValue);
        }

        // Log discount application for audit purposes
        if ($discountedPrice < $basePrice) {
            Log::info('Membership discount applied', [
                'base_price' => $basePrice,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discounted_price' => $discountedPrice,
                'savings' => $basePrice - $discountedPrice,
            ]);
        }

        return $discountedPrice;
    }

    /**
     * Get pricing information for a ticket definition including membership discounts.
     */
    public function getPricingInfo(TicketDefinition $ticketDefinition, ?User $user = null): array
    {
        $basePrice = $ticketDefinition->price;
        $membershipPrice = null;
        $hasMembershipDiscount = false;
        $savingsAmount = 0;
        $savingsPercentage = 0;

        if ($user) {
            $membershipPrice = $this->calculateMembershipPrice($ticketDefinition, $user);
            $hasMembershipDiscount = $membershipPrice < $basePrice;

            if ($hasMembershipDiscount) {
                $savingsAmount = $basePrice - $membershipPrice;
                $savingsPercentage = ($savingsAmount / $basePrice) * 100;
            }
        }

        return [
            'base_price' => $basePrice,
            'base_price_formatted' => $basePrice / 100,
            'membership_price' => $membershipPrice,
            'membership_price_formatted' => $membershipPrice ? $membershipPrice / 100 : null,
            'has_membership_discount' => $hasMembershipDiscount,
            'savings_amount' => $savingsAmount,
            'savings_amount_formatted' => $savingsAmount / 100,
            'savings_percentage' => round($savingsPercentage, 1),
            'currency' => $ticketDefinition->currency,
        ];
    }

    /**
     * Check if a user is eligible for membership discounts on a ticket definition.
     */
    public function isEligibleForMembershipDiscount(TicketDefinition $ticketDefinition, User $user): bool
    {
        $activeMembershipLevel = $user->getActiveMembershipLevel();

        if (!$activeMembershipLevel) {
            return false;
        }

        return $ticketDefinition->hasMembershipDiscount($activeMembershipLevel);
    }

    /**
     * Get all available discounts for a ticket definition.
     */
    public function getAvailableDiscounts(TicketDefinition $ticketDefinition): array
    {
        return $ticketDefinition->membershipDiscounts()
            ->with('level')
            ->get()
            ->map(function ($membershipLevel) {
                return [
                    'membership_level_id' => $membershipLevel->id,
                    'membership_level_name' => $membershipLevel->name,
                    'discount_type' => $membershipLevel->pivot->discount_type,
                    'discount_value' => $membershipLevel->pivot->discount_value,
                    'formatted_discount' => $this->formatDiscountValue(
                        $membershipLevel->pivot->discount_type,
                        $membershipLevel->pivot->discount_value
                    ),
                ];
            })
            ->toArray();
    }

    /**
     * Format a discount value for display.
     */
    protected function formatDiscountValue(string $discountType, int $discountValue): string
    {
        if ($discountType === 'percentage') {
            return "{$discountValue}%";
        } elseif ($discountType === 'fixed') {
            return '$' . number_format($discountValue / 100, 2);
        }

        return (string) $discountValue;
    }
}
