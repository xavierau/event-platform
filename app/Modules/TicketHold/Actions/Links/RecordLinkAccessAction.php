<?php

namespace App\Modules\TicketHold\Actions\Links;

use App\Models\User;
use App\Modules\TicketHold\Models\PurchaseLink;
use App\Modules\TicketHold\Models\PurchaseLinkAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordLinkAccessAction
{
    private const MAX_USER_AGENT_LENGTH = 500;

    /**
     * Record an access to a purchase link for analytics.
     *
     * @param  Request|null  $request  HTTP request for extracting metadata
     * @param  User|null  $user  Authenticated user, if any
     */
    public function execute(
        PurchaseLink $link,
        ?Request $request = null,
        ?User $user = null
    ): PurchaseLinkAccess {
        $access = PurchaseLinkAccess::create([
            'purchase_link_id' => $link->id,
            'user_id' => $user?->id,
            'ip_address' => $this->validateIpAddress($request?->ip()),
            'user_agent' => $this->truncateUserAgent($request?->userAgent()),
            'referer' => $request?->header('referer'),
            'session_id' => $request?->session()?->getId(),
            'resulted_in_purchase' => false,
            'accessed_at' => now(),
        ]);

        return $access;
    }

    /**
     * Validate IP address format.
     */
    private function validateIpAddress(?string $ipAddress): ?string
    {
        if ($ipAddress === null) {
            return null;
        }

        return filter_var($ipAddress, FILTER_VALIDATE_IP) ? $ipAddress : null;
    }

    /**
     * Truncate user agent string to prevent excessively long values.
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return Str::limit($userAgent, self::MAX_USER_AGENT_LENGTH, '');
    }

    /**
     * Record access from raw data (useful for API calls or queued jobs).
     */
    public function executeFromData(PurchaseLink $link, array $accessData): PurchaseLinkAccess
    {
        return PurchaseLinkAccess::create([
            'purchase_link_id' => $link->id,
            'user_id' => $accessData['user_id'] ?? null,
            'ip_address' => $this->validateIpAddress($accessData['ip_address'] ?? null),
            'user_agent' => $this->truncateUserAgent($accessData['user_agent'] ?? null),
            'referer' => $accessData['referer'] ?? null,
            'session_id' => $accessData['session_id'] ?? null,
            'resulted_in_purchase' => false,
            'accessed_at' => $accessData['accessed_at'] ?? now(),
        ]);
    }
}
