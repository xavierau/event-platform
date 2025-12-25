<?php

namespace App\Modules\TicketHold\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketHold\StorePurchaseLinkRequest;
use App\Http\Requests\TicketHold\UpdatePurchaseLinkRequest;
use App\Models\User;
use App\Modules\TicketHold\Actions\Links\CreatePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Links\RevokePurchaseLinkAction;
use App\Modules\TicketHold\Actions\Links\UpdatePurchaseLinkAction;
use App\Modules\TicketHold\DTOs\PurchaseLinkData;
use App\Modules\TicketHold\Enums\QuantityModeEnum;
use App\Modules\TicketHold\Exceptions\HoldNotActiveException;
use App\Modules\TicketHold\Exceptions\LinkNotUsableException;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\TicketHold;
use App\Modules\TicketHold\Services\HoldAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PurchaseLinkController extends Controller
{
    public function __construct(
        protected CreatePurchaseLinkAction $createAction,
        protected UpdatePurchaseLinkAction $updateAction,
        protected RevokePurchaseLinkAction $revokeAction,
        protected HoldAnalyticsService $analyticsService
    ) {}

    /**
     * Store a newly created purchase link.
     */
    public function store(StorePurchaseLinkRequest $request, TicketHold $ticketHold): RedirectResponse
    {
        $this->authorize('createLink', $ticketHold);

        $validated = $request->validated();

        try {
            $linkData = new PurchaseLinkData(
                ticket_hold_id: $ticketHold->id,
                name: $validated['name'] ?? null,
                assigned_user_id: $validated['assigned_user_id'] ?? null,
                quantity_mode: QuantityModeEnum::from($validated['quantity_mode']),
                quantity_limit: $validated['quantity_limit'] ?? null,
                expires_at: isset($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : null,
                notes: $validated['notes'] ?? null,
                metadata: null,
            );

            $link = $this->createAction->execute($linkData);

            return redirect()->route('admin.ticket-holds.show', $ticketHold)
                ->with('success', "Purchase link created successfully. Code: {$link->code}");
        } catch (HoldNotActiveException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified purchase link.
     */
    public function show(PurchaseLink $purchaseLink): InertiaResponse
    {
        $this->authorize('view', $purchaseLink);

        $purchaseLink->load([
            'ticketHold.organizer',
            'ticketHold.eventOccurrence.event',
            'ticketHold.allocations.ticketDefinition',
            'assignedUser',
            'revokedByUser',
            'accesses',
            'purchases.booking',
        ]);

        $analytics = $this->analyticsService->getLinkAnalytics($purchaseLink);

        return Inertia::render('Admin/TicketHolds/PurchaseLinks/Show', [
            'pageTitle' => "Purchase Link: {$purchaseLink->name}",
            'breadcrumbs' => [
                ['text' => 'Admin', 'href' => route('admin.dashboard')],
                ['text' => 'Ticket Holds', 'href' => route('admin.ticket-holds.index')],
                ['text' => $purchaseLink->ticketHold->name, 'href' => route('admin.ticket-holds.show', $purchaseLink->ticketHold)],
                ['text' => 'Purchase Link'],
            ],
            'purchaseLink' => [
                'id' => $purchaseLink->id,
                'uuid' => $purchaseLink->uuid,
                'code' => $purchaseLink->code,
                'name' => $purchaseLink->name,
                'full_url' => $purchaseLink->full_url,
                'status' => $purchaseLink->status->value,
                'status_label' => $purchaseLink->status->label(),
                'quantity_mode' => $purchaseLink->quantity_mode->value,
                'quantity_mode_label' => $purchaseLink->quantity_mode->label(),
                'quantity_limit' => $purchaseLink->quantity_limit,
                'quantity_purchased' => $purchaseLink->quantity_purchased,
                'remaining_quantity' => $purchaseLink->remaining_quantity,
                'is_anonymous' => $purchaseLink->is_anonymous,
                'is_usable' => $purchaseLink->is_usable,
                'notes' => $purchaseLink->notes,
                'expires_at' => $purchaseLink->expires_at?->toIso8601String(),
                'revoked_at' => $purchaseLink->revoked_at?->toIso8601String(),
                'created_at' => $purchaseLink->created_at->toIso8601String(),
                'assigned_user' => $purchaseLink->assignedUser ? [
                    'id' => $purchaseLink->assignedUser->id,
                    'name' => $purchaseLink->assignedUser->name,
                    'email' => $purchaseLink->assignedUser->email,
                ] : null,
                'revoked_by' => $purchaseLink->revokedByUser ? [
                    'id' => $purchaseLink->revokedByUser->id,
                    'name' => $purchaseLink->revokedByUser->name,
                ] : null,
                'ticket_hold' => [
                    'id' => $purchaseLink->ticketHold->id,
                    'name' => $purchaseLink->ticketHold->name,
                    'event_name' => $purchaseLink->ticketHold->eventOccurrence->event->getTranslation('name', app()->getLocale()),
                ],
            ],
            'analytics' => $analytics,
        ]);
    }

    /**
     * Update the specified purchase link.
     */
    public function update(UpdatePurchaseLinkRequest $request, PurchaseLink $purchaseLink): RedirectResponse
    {
        $this->authorize('update', $purchaseLink);

        $validated = $request->validated();

        try {
            $linkData = new PurchaseLinkData(
                ticket_hold_id: $purchaseLink->ticket_hold_id,
                name: $validated['name'] ?? $purchaseLink->name,
                assigned_user_id: $purchaseLink->assigned_user_id,
                quantity_mode: $purchaseLink->quantity_mode,
                quantity_limit: $purchaseLink->quantity_limit,
                expires_at: isset($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : $purchaseLink->expires_at,
                notes: $validated['notes'] ?? $purchaseLink->notes,
                metadata: $purchaseLink->metadata,
            );

            $this->updateAction->execute($purchaseLink, $linkData);

            return redirect()->route('admin.purchase-links.show', $purchaseLink)
                ->with('success', 'Purchase link updated successfully.');
        } catch (LinkNotUsableException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified purchase link.
     */
    public function destroy(PurchaseLink $purchaseLink): RedirectResponse
    {
        $this->authorize('delete', $purchaseLink);

        $ticketHold = $purchaseLink->ticketHold;
        $purchaseLink->delete();

        return redirect()->route('admin.ticket-holds.show', $ticketHold)
            ->with('success', 'Purchase link deleted successfully.');
    }

    /**
     * Revoke the specified purchase link.
     */
    public function revoke(PurchaseLink $purchaseLink): RedirectResponse
    {
        $this->authorize('revoke', $purchaseLink);

        $this->revokeAction->execute($purchaseLink, auth()->user());

        return redirect()->route('admin.purchase-links.show', $purchaseLink)
            ->with('success', 'Purchase link revoked successfully.');
    }

    /**
     * Search users for assignment (JSON endpoint).
     */
    public function searchUsers(Request $request): JsonResponse
    {
        // Authorization check - only users who can create purchase links can search users
        $this->authorize('create', PurchaseLink::class);

        $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        // Escape SQL LIKE wildcards to prevent wildcard injection
        $query = str_replace(['%', '_'], ['\%', '\_'], $request->input('query'));

        $users = User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%");
        })
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'label' => "{$user->name} ({$user->email})",
            ]);

        return response()->json([
            'users' => $users,
        ]);
    }
}
