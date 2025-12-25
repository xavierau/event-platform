<?php

namespace App\Modules\TicketHold\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketHold\ProcessHoldPurchaseRequest;
use App\Modules\TicketHold\Actions\Links\RecordLinkAccessAction;
use App\Modules\TicketHold\Actions\Purchases\CalculateHoldPriceAction;
use App\Modules\TicketHold\Actions\Purchases\ProcessHoldPurchaseAction;
use App\Modules\TicketHold\DTOs\HoldPurchaseItemData;
use App\Modules\TicketHold\DTOs\HoldPurchaseRequestData;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Exceptions\InsufficientHoldInventoryException;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Exceptions\UserNotAuthorizedForLinkException;
use App\Modules\TicketHold\Models\PurchaseLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PurchaseLinkController extends Controller
{
    public function __construct(
        protected RecordLinkAccessAction $recordAccessAction,
        protected CalculateHoldPriceAction $calculatePriceAction,
        protected ProcessHoldPurchaseAction $processPurchaseAction
    ) {}

    /**
     * Display the public purchase link landing page.
     */
    public function show(Request $request, string $code): InertiaResponse|RedirectResponse
    {
        $link = PurchaseLink::byCode($code)
            ->with([
                'ticketHold.eventOccurrence.event',
                'ticketHold.allocations.ticketDefinition',
                'ticketHold.organizer',
            ])
            ->first();

        if (! $link) {
            return Inertia::render('Public/PurchaseLink/NotFound', [
                'message' => 'This purchase link was not found or is no longer available.',
            ]);
        }

        $user = auth()->user();

        // Record access for analytics
        $access = $this->recordAccessAction->execute($link, $request, $user);

        // Store access ID in session for later use during purchase
        session(['purchase_link_access_id' => $access->id]);

        // Check if link can be used
        if (! $link->is_usable) {
            return Inertia::render('Public/PurchaseLink/Unavailable', [
                'message' => $this->getUnavailableMessage($link),
                'link' => [
                    'code' => $link->code,
                    'name' => $link->name,
                    'status' => $link->status->value,
                    'status_label' => $link->status->label(),
                ],
            ]);
        }

        // Check if user is authorized for user-tied links
        if (! $link->is_anonymous && ! $link->canBeUsedByUser($user)) {
            if (! $user) {
                return redirect()->route('login', ['redirect' => route('purchase-link.show', ['code' => $code])]);
            }

            return Inertia::render('Public/PurchaseLink/Unauthorized', [
                'message' => 'This purchase link is assigned to a specific user and cannot be used with your account.',
            ]);
        }

        $hold = $link->ticketHold;
        $event = $hold->eventOccurrence->event;

        // Calculate prices for each allocation
        $allocations = $hold->allocations->map(function ($allocation) {
            $priceInfo = $this->calculatePriceAction->execute($allocation);

            return [
                'id' => $allocation->id,
                'ticket_hold_id' => $allocation->ticket_hold_id,
                'ticket_definition_id' => $allocation->ticket_definition_id,
                'ticket_definition' => [
                    'id' => $allocation->ticketDefinition->id,
                    'name' => $allocation->ticketDefinition->getTranslations('name'),
                    'description' => $allocation->ticketDefinition->getTranslations('description'),
                    'currency' => $allocation->ticketDefinition->currency ?? 'USD',
                ],
                'allocated_quantity' => $allocation->allocated_quantity,
                'purchased_quantity' => $allocation->purchased_quantity,
                'remaining_quantity' => $allocation->remaining_quantity,
                'pricing_mode' => $allocation->pricing_mode->value,
                'effective_price' => $priceInfo['unit_price'], // in cents
                'original_price' => $priceInfo['original_price'], // in cents
                'savings' => $priceInfo['savings'], // in cents
                'savings_percentage' => $priceInfo['savings_percentage'],
                'is_free' => $priceInfo['unit_price'] === 0,
            ];
        });

        // Get venue name
        $venue = $hold->eventOccurrence->venue ?? $event->venue ?? null;
        $venueName = $venue ? ($venue->getTranslation('name', app()->getLocale()) ?? $venue->name) : '';

        return Inertia::render('Public/PurchaseLink/Show', [
            'pageTitle' => "Purchase Tickets - {$event->getTranslation('name', app()->getLocale())}",
            'link' => [
                'code' => $link->code,
                'name' => $link->name,
                'status' => $link->status->value,
                'quantity_mode' => $link->quantity_mode->value,
                'quantity_mode_label' => $link->quantity_mode->label(),
                'quantity_limit' => $link->quantity_limit,
                'remaining_quantity' => $link->remaining_quantity,
                'is_anonymous' => $link->is_anonymous,
                'expires_at' => $link->expires_at?->toIso8601String(),
            ],
            'hold' => [
                'name' => $hold->name,
                'description' => $hold->description,
            ],
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('name', app()->getLocale()),
                'description' => $event->getTranslation('description', app()->getLocale()),
                'date' => $hold->eventOccurrence->start_at->toIso8601String(),
                'venue' => $venueName,
                'image_url' => $event->getFirstMediaUrl('portrait_poster', 'medium')
                    ?: $event->getFirstMediaUrl('landscape_poster', 'medium'),
            ],
            'occurrence' => [
                'id' => $hold->eventOccurrence->id,
                'start_at' => $hold->eventOccurrence->start_at->toIso8601String(),
                'end_at' => $hold->eventOccurrence->end_at?->toIso8601String(),
            ],
            'organizer' => $hold->organizer ? [
                'name' => $hold->organizer->name,
                'logo_url' => $hold->organizer->logo_url ?? null,
            ] : null,
            'allocations' => $allocations,
            'isUsable' => true, // We've already validated the link is usable at this point
            'canPurchase' => true, // User is authorized (checked above)
            'isAuthenticated' => $user !== null,
            'user' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }

    /**
     * Process the purchase through the link.
     */
    public function purchase(ProcessHoldPurchaseRequest $request, string $code): RedirectResponse
    {
        $link = PurchaseLink::byCode($code)->first();

        if (! $link) {
            return redirect()->route('home')
                ->with('error', 'Purchase link not found.');
        }

        $validated = $request->validated();
        $user = auth()->user();

        try {
            // Get the access record from session and validate it belongs to this link
            $accessId = session('purchase_link_access_id');
            $access = null;
            if ($accessId) {
                $access = \App\Modules\TicketHold\Models\PurchaseLinkAccess::where('id', $accessId)
                    ->where('purchase_link_id', $link->id)
                    ->first();
            }

            // Create purchase request data
            $items = array_map(function ($item) {
                return new HoldPurchaseItemData(
                    ticket_definition_id: $item['ticket_definition_id'],
                    quantity: $item['quantity']
                );
            }, $validated['items']);

            $requestData = new HoldPurchaseRequestData(
                link_code: $code,
                items: $items,
                coupon_code: $validated['coupon_code'] ?? null
            );

            $result = $this->processPurchaseAction->execute($requestData, $user, $access);

            // Clear the access ID from session
            session()->forget('purchase_link_access_id');

            return redirect()->route('my-bookings')
                ->with('success', 'Purchase completed successfully! Your tickets have been added to your bookings.');
        } catch (LinkNotUsableException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        } catch (HoldNotActiveException $e) {
            return redirect()->back()
                ->with('error', 'This ticket hold is no longer active.');
        } catch (InsufficientHoldInventoryException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        } catch (UserNotAuthorizedForLinkException $e) {
            return redirect()->back()
                ->with('error', 'You are not authorized to use this purchase link.');
        }
    }

    /**
     * Get an appropriate message for unavailable links.
     */
    private function getUnavailableMessage(PurchaseLink $link): string
    {
        if ($link->is_expired) {
            return 'This purchase link has expired.';
        }

        if (! $link->status->isUsable()) {
            return match ($link->status->value) {
                'revoked' => 'This purchase link has been revoked.',
                'exhausted' => 'All tickets available through this link have been purchased.',
                'expired' => 'This purchase link has expired.',
                default => 'This purchase link is no longer active.',
            };
        }

        if (! $link->ticketHold->is_usable) {
            return 'The ticket hold associated with this link is no longer active.';
        }

        if ($link->remaining_quantity === 0) {
            return 'All tickets available through this link have been purchased.';
        }

        return 'This purchase link is not available.';
    }
}
