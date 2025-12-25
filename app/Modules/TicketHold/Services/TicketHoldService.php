<?php

namespace App\Modules\TicketHold\Services;

use App\Models\User;
use App\Modules\TicketHold\Actions\Holds\CreateTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\ReleaseTicketHoldAction;
use App\Modules\TicketHold\Actions\Holds\UpdateTicketHoldAction;
use App\Modules\TicketHold\Actions\Links\CreatePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Links\RecordLinkAccessAction;
use App\Modules\TicketHold\Actions\Links\RevokePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Links\UpdatePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Purchases\CalculateHoldPriceAction;
use App\Modules\TicketHold\Actions\Purchases\ProcessHoldPurchaseAction;
use App\Modules\TicketHold\DTOs\HoldPurchaseRequestData;
use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\DTOs\TicketHoldData;
use App\Modules\TicketHold\Enums\HoldStatusEnum;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Exceptions\InsufficientHoldInventoryException;
use App\Modules\TicketHold\Exceptions\InsufficientInventoryException;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Exceptions\UserNotAuthorizedForLinkException;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use App\Modules\TicketHold\Models\TicketHold;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TicketHoldService
{
    public function __construct(
        private CreateTicketHoldAction $createHoldAction,
        private UpdateTicketHoldAction $updateHoldAction,
        private ReleaseTicketHoldAction $releaseHoldAction,
        private CreatePurchaseLinkAction $createLinkAction,
        private UpdatePurchaseLinkAction $updateLinkAction,
        private RevokePurchaseLinkAction $revokeLinkAction,
        private RecordLinkAccessAction $recordAccessAction,
        private CalculateHoldPriceAction $calculatePriceAction,
        private ProcessHoldPurchaseAction $processPurchaseAction,
    ) {}

    // ========================================
    // Hold Operations
    // ========================================

    /**
     * Create a new ticket hold.
     *
     * @throws InsufficientInventoryException
     */
    public function createHold(TicketHoldData $data, User $creator): TicketHold
    {
        return $this->createHoldAction->execute($data, $creator);
    }

    /**
     * Update an existing ticket hold.
     */
    public function updateHold(TicketHold $hold, TicketHoldData $data): TicketHold
    {
        return $this->updateHoldAction->execute($hold, $data);
    }

    /**
     * Release a ticket hold, making tickets available for public sale.
     */
    public function releaseHold(TicketHold $hold, User $releasedBy): TicketHold
    {
        return $this->releaseHoldAction->execute($hold, $releasedBy);
    }

    /**
     * Get a ticket hold by ID.
     */
    public function getHoldById(int $holdId): ?TicketHold
    {
        return TicketHold::with([
            'allocations.ticketDefinition',
            'eventOccurrence.event',
            'purchaseLinks',
            'creator',
        ])->find($holdId);
    }

    /**
     * Get a ticket hold by UUID.
     */
    public function getHoldByUuid(string $uuid): ?TicketHold
    {
        return TicketHold::with([
            'allocations.ticketDefinition',
            'eventOccurrence.event',
            'purchaseLinks',
            'creator',
        ])->where('uuid', $uuid)->first();
    }

    /**
     * Get holds for an organizer.
     *
     * @return Collection<TicketHold>
     */
    public function getHoldsForOrganizer(int $organizerId, ?HoldStatusEnum $status = null): Collection
    {
        $query = TicketHold::with(['allocations.ticketDefinition', 'eventOccurrence.event'])
            ->forOrganizer($organizerId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Get holds for an event occurrence.
     *
     * @return Collection<TicketHold>
     */
    public function getHoldsForOccurrence(int $occurrenceId, ?HoldStatusEnum $status = null): Collection
    {
        $query = TicketHold::with(['allocations.ticketDefinition', 'purchaseLinks'])
            ->forOccurrence($occurrenceId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    // ========================================
    // Link Operations
    // ========================================

    /**
     * Create a purchase link for a hold.
     *
     * @throws HoldNotActiveException
     */
    public function createPurchaseLink(PurchaseLinkData $data): PurchaseLink
    {
        return $this->createLinkAction->execute($data);
    }

    /**
     * Update a purchase link.
     *
     * @throws LinkNotUsableException
     */
    public function updatePurchaseLink(PurchaseLink $link, PurchaseLinkData $data): PurchaseLink
    {
        return $this->updateLinkAction->execute($link, $data);
    }

    /**
     * Revoke a purchase link.
     */
    public function revokePurchaseLink(PurchaseLink $link, User $revokedBy): PurchaseLink
    {
        return $this->revokeLinkAction->execute($link, $revokedBy);
    }

    /**
     * Get a purchase link by code.
     */
    public function getLinkByCode(string $code): ?PurchaseLink
    {
        return PurchaseLink::with([
            'ticketHold.allocations.ticketDefinition',
            'ticketHold.eventOccurrence.event',
            'assignedUser',
        ])->byCode($code)->first();
    }

    /**
     * Get a purchase link by ID.
     */
    public function getLinkById(int $linkId): ?PurchaseLink
    {
        return PurchaseLink::with([
            'ticketHold.allocations.ticketDefinition',
            'ticketHold.eventOccurrence.event',
            'assignedUser',
        ])->find($linkId);
    }

    /**
     * Get all links for a hold.
     *
     * @return Collection<PurchaseLink>
     */
    public function getLinksForHold(TicketHold $hold): Collection
    {
        return $hold->purchaseLinks()
            ->with(['assignedUser', 'accesses', 'purchases'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ========================================
    // Access & Purchase Operations
    // ========================================

    /**
     * Record link access for analytics.
     */
    public function recordLinkAccess(
        PurchaseLink $link,
        ?Request $request = null,
        ?User $user = null
    ): PurchaseLinkAccess {
        return $this->recordAccessAction->execute($link, $request, $user);
    }

    /**
     * Process a purchase through a hold link.
     *
     * @throws LinkNotUsableException
     * @throws HoldNotActiveException
     * @throws InsufficientHoldInventoryException
     * @throws UserNotAuthorizedForLinkException
     */
    public function processPurchase(
        HoldPurchaseRequestData $requestData,
        ?User $user = null,
        ?PurchaseLinkAccess $access = null
    ): array {
        return $this->processPurchaseAction->execute($requestData, $user, $access);
    }

    /**
     * Calculate order total for items through a link.
     *
     * @param  array<array{ticket_definition_id: int, quantity: int}>  $items
     */
    public function calculateOrderTotal(PurchaseLink $link, array $items): array
    {
        return $this->calculatePriceAction->calculateOrderTotal($link, $items);
    }

    /**
     * Validate if a link can be used by a user.
     *
     * @return array{valid: bool, link: PurchaseLink|null, errors: array}
     */
    public function validateLinkForUser(string $code, ?User $user = null): array
    {
        $link = $this->getLinkByCode($code);

        if (! $link) {
            return [
                'valid' => false,
                'link' => null,
                'errors' => ['Link not found'],
            ];
        }

        $errors = [];

        if (! $link->is_usable) {
            $errors[] = "Link is not usable. Status: {$link->status->label()}";
        }

        if (! $link->canBeUsedByUser($user)) {
            $errors[] = 'You are not authorized to use this link';
        }

        if (! $link->ticketHold->is_usable) {
            $errors[] = 'The associated ticket hold is not active';
        }

        return [
            'valid' => empty($errors),
            'link' => $link,
            'errors' => $errors,
        ];
    }

    // ========================================
    // Utility Operations
    // ========================================

    /**
     * Check and update expired holds.
     *
     * @return int Number of holds updated
     */
    public function updateExpiredHolds(): int
    {
        $expiredHolds = TicketHold::where('status', HoldStatusEnum::ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredHolds as $hold) {
            $hold->checkAndUpdateExpiration();
        }

        return $expiredHolds->count();
    }

    /**
     * Check and update expired links.
     *
     * @return int Number of links updated
     */
    public function updateExpiredLinks(): int
    {
        $expiredLinks = PurchaseLink::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredLinks as $link) {
            $link->checkAndUpdateExpiration();
        }

        return $expiredLinks->count();
    }

    /**
     * Delete a ticket hold (soft delete).
     */
    public function deleteHold(TicketHold $hold): bool
    {
        return $hold->delete();
    }

    /**
     * Delete a purchase link (soft delete).
     */
    public function deleteLink(PurchaseLink $link): bool
    {
        return $link->delete();
    }
}
