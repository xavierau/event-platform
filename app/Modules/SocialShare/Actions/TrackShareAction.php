<?php

namespace App\Modules\SocialShare\Actions;

use App\Models\User;
use App\Modules\SocialShare\Data\ShareAnalyticData;
use App\Modules\SocialShare\Models\SocialShareAnalytic;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackShareAction
{
    /**
     * Track a social share action
     *
     * @throws Exception
     */
    public function execute(ShareAnalyticData $data): SocialShareAnalytic
    {
        // Check rate limiting if enabled
        if (config('social-share.rate_limiting.enabled', true)) {
            $this->checkRateLimit($data);
        }

        // Create the analytic record
        return SocialShareAnalytic::create([
            'shareable_type' => $data->shareable_type,
            'shareable_id' => $data->shareable_id,
            'platform' => $data->platform,
            'user_id' => $data->user_id,
            'ip_address' => $data->ip_address,
            'user_agent' => $data->user_agent,
            'referrer' => $data->referrer,
            'metadata' => $data->metadata,
        ]);
    }

    /**
     * Track share from HTTP request
     */
    public function trackFromRequest(Request $request, ?User $user = null): SocialShareAnalytic
    {
        $analyticData = ShareAnalyticData::from([
            'shareable_type' => $request->input('shareable_type'),
            'shareable_id' => $request->input('shareable_id'),
            'platform' => $request->input('platform'),
            'user_id' => $user?->id,
            'ip_address' => $this->getClientIp($request),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'metadata' => $request->input('metadata', []),
        ]);

        return $this->execute($analyticData);
    }

    /**
     * Track multiple shares at once
     */
    public function trackMultiple(array $shareDataArray): array
    {
        $analytics = [];

        foreach ($shareDataArray as $shareData) {
            $analyticData = ShareAnalyticData::from($shareData);
            $analytics[] = $this->execute($analyticData);
        }

        return $analytics;
    }

    /**
     * Track multiple shares safely (with error handling)
     */
    public function trackMultipleSafe(array $shareDataArray): array
    {
        $successful = [];
        $failed = [];

        foreach ($shareDataArray as $index => $shareData) {
            try {
                $analyticData = ShareAnalyticData::from($shareData);
                $analytic = $this->execute($analyticData);
                $successful[] = $analytic;
            } catch (Exception $e) {
                $failed[] = [
                    'index' => $index,
                    'data' => $shareData,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Check rate limiting for the share request
     *
     * @throws Exception
     */
    private function checkRateLimit(ShareAnalyticData $data): void
    {
        $config = config('social-share.rate_limiting', []);

        if (! $config['enabled']) {
            return;
        }

        $maxSharesPerIp = $config['max_shares_per_ip_per_hour'] ?? 50;
        $maxSharesPerUser = $config['max_shares_per_user_per_hour'] ?? 100;

        // Check IP-based rate limiting
        $ipKey = "social_share_limit:ip:{$data->ip_address}";
        $ipShares = Cache::get($ipKey, 0);

        if ($ipShares >= $maxSharesPerIp) {
            throw new Exception('Rate limit exceeded for IP address.');
        }

        // Check user-based rate limiting (if authenticated)
        if ($data->user_id) {
            $userKey = "social_share_limit:user:{$data->user_id}";
            $userShares = Cache::get($userKey, 0);

            if ($userShares >= $maxSharesPerUser) {
                throw new Exception('Rate limit exceeded for user.');
            }

            // Increment user counter
            Cache::put($userKey, $userShares + 1, now()->addHour());
        }

        // Increment IP counter
        Cache::put($ipKey, $ipShares + 1, now()->addHour());
    }

    /**
     * Get the client IP address from request
     */
    private function getClientIp(Request $request): string
    {
        // Check for shared internet/proxy
        if (! empty($request->server('HTTP_CLIENT_IP'))) {
            return $request->server('HTTP_CLIENT_IP');
        }
        // Check for IP passed from proxy
        elseif (! empty($request->server('HTTP_X_FORWARDED_FOR'))) {
            // Can contain multiple IPs, get the first one
            $ips = explode(',', $request->server('HTTP_X_FORWARDED_FOR'));

            return trim($ips[0]);
        }
        // Check for remote IP
        elseif (! empty($request->server('REMOTE_ADDR'))) {
            return $request->server('REMOTE_ADDR');
        }

        // Fallback to localhost
        return '127.0.0.1';
    }

    /**
     * Get share analytics for a specific model
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAnalytics(string $shareableType, int $shareableId, array $filters = [])
    {
        $query = SocialShareAnalytic::where('shareable_type', $shareableType)
            ->where('shareable_id', $shareableId);

        // Apply filters
        if (isset($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['authenticated_only']) && $filters['authenticated_only']) {
            $query->whereNotNull('user_id');
        }

        if (isset($filters['anonymous_only']) && $filters['anonymous_only']) {
            $query->whereNull('user_id');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get aggregated share statistics
     */
    public function getStatistics(?string $shareableType = null, ?int $shareableId = null, array $filters = []): array
    {
        $query = SocialShareAnalytic::query();

        if ($shareableType && $shareableId) {
            $query->where('shareable_type', $shareableType)
                ->where('shareable_id', $shareableId);
        }

        // Apply date filters
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Get total shares
        $totalShares = $query->count();

        // Get shares by platform
        $sharesByPlatform = $query->selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();

        // Get authenticated vs anonymous breakdown
        $authenticatedShares = $query->whereNotNull('user_id')->count();
        $anonymousShares = $totalShares - $authenticatedShares;

        // Get mobile vs desktop breakdown
        $mobileShares = $query->where('user_agent', 'LIKE', '%Mobile%')
            ->orWhere('user_agent', 'LIKE', '%iPhone%')
            ->orWhere('user_agent', 'LIKE', '%Android%')
            ->count();
        $desktopShares = $totalShares - $mobileShares;

        return [
            'total_shares' => $totalShares,
            'shares_by_platform' => $sharesByPlatform,
            'authenticated_shares' => $authenticatedShares,
            'anonymous_shares' => $anonymousShares,
            'mobile_shares' => $mobileShares,
            'desktop_shares' => $desktopShares,
        ];
    }

    /**
     * Clean up old analytics data based on retention policy
     *
     * @return int Number of records deleted
     */
    public function cleanupOldAnalytics(): int
    {
        $retentionDays = config('social-share.analytics.retention_days', 365);
        $cutoffDate = now()->subDays($retentionDays);

        return SocialShareAnalytic::where('created_at', '<', $cutoffDate)->delete();
    }
}
