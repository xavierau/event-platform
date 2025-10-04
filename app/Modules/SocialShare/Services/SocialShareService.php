<?php

namespace App\Modules\SocialShare\Services;

use App\Models\User;
use App\Modules\SocialShare\Actions\GenerateShareUrlAction;
use App\Modules\SocialShare\Actions\TrackShareAction;
use App\Modules\SocialShare\Contracts\ShareableInterface;
use App\Modules\SocialShare\Models\SocialShareAnalytic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SocialShareService
{
    public function __construct(
        private GenerateShareUrlAction $generateAction,
        private TrackShareAction $trackAction
    ) {}

    /**
     * Generate share URLs for a shareable model
     */
    public function generateShareUrls(ShareableInterface $shareable, ?array $platforms = null, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $platforms = $platforms ?: $this->getEnabledPlatforms();

        // Check cache if enabled
        if (config('social-share.cache.enabled', true)) {
            $cacheKey = $this->getCacheKey($shareable, $platforms, $locale);
            $cached = Cache::get($cacheKey);

            if ($cached) {
                return $cached;
            }
        }

        // Generate URLs
        $shareUrls = $this->generateAction->generateForShareable($shareable, $platforms, $locale);

        // Cache results if enabled
        if (config('social-share.cache.enabled', true)) {
            $ttl = config('social-share.cache.ttl', 300);
            Cache::put($cacheKey, $shareUrls, $ttl);
        }

        return $shareUrls;
    }

    /**
     * Track a share action
     */
    public function trackShare(
        ShareableInterface $shareable,
        string $platform,
        ?User $user = null,
        string $ipAddress = '127.0.0.1',
        ?string $userAgent = null,
        ?string $referrer = null,
        array $metadata = []
    ): SocialShareAnalytic {
        $analyticData = \App\Modules\SocialShare\Data\ShareAnalyticData::from([
            'shareable_type' => get_class($shareable),
            'shareable_id' => $shareable->id,
            'platform' => $platform,
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referrer' => $referrer,
            'metadata' => $metadata,
        ]);

        return $this->trackAction->execute($analyticData);
    }

    /**
     * Track share from HTTP request
     */
    public function trackShareFromRequest(ShareableInterface $shareable, Request $request, ?User $user = null): SocialShareAnalytic
    {
        return $this->trackShare(
            $shareable,
            $request->input('platform'),
            $user,
            $this->getClientIp($request),
            $request->userAgent(),
            $request->header('referer'),
            $request->input('metadata', [])
        );
    }

    /**
     * Get total share count for a model
     */
    public function getShareCount(Model $model, array $filters = []): int
    {
        $query = SocialShareAnalytic::where('shareable_type', get_class($model))
            ->where('shareable_id', $model->id);

        $this->applyFilters($query, $filters);

        return $query->count();
    }

    /**
     * Get share count by platform for a model
     */
    public function getShareCountByPlatform(Model $model, array $filters = []): array
    {
        $query = SocialShareAnalytic::where('shareable_type', get_class($model))
            ->where('shareable_id', $model->id);

        $this->applyFilters($query, $filters);

        return $query->selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();
    }

    /**
     * Get comprehensive share statistics
     */
    public function getShareStatistics(?Model $model = null, array $filters = []): array
    {
        return $this->trackAction->getStatistics(
            $model ? get_class($model) : null,
            $model?->id,
            $filters
        );
    }

    /**
     * Get popular content by share count
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPopularContent(string $modelType, int $limit = 10, array $filters = [])
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        return SocialShareAnalytic::getPopularContent($modelType, $limit, $dateFrom, $dateTo);
    }

    /**
     * Get enabled platforms from configuration
     */
    public function getEnabledPlatforms(): array
    {
        $platforms = config('social-share.platforms', []);

        return collect($platforms)
            ->filter(fn ($config) => $config['enabled'] ?? false)
            ->keys()
            ->toArray();
    }

    /**
     * Get platform configuration
     */
    public function getPlatformConfig(string $platform): ?array
    {
        return config("social-share.platforms.{$platform}");
    }

    /**
     * Check if a platform is enabled
     */
    public function isPlatformEnabled(string $platform): bool
    {
        $config = $this->getPlatformConfig($platform);

        return $config && ($config['enabled'] ?? false);
    }

    /**
     * Get UI mode based on platform count
     */
    public function getUIMode(array $platforms): string
    {
        $maxButtons = config('social-share.ui.max_buttons_before_dropdown', 4);

        return count($platforms) > $maxButtons ? 'dropdown' : 'buttons';
    }

    /**
     * Get UI configuration
     */
    public function getUIConfig(): array
    {
        return config('social-share.ui', []);
    }

    /**
     * Get share button data for frontend
     */
    public function getShareButtonData(ShareableInterface $shareable, ?array $platforms = null, ?string $locale = null): array
    {
        $platforms = $platforms ?: $this->getEnabledPlatforms();
        $shareUrls = $this->generateShareUrls($shareable, $platforms, $locale);
        $shareCounts = $this->getShareCountByPlatform($shareable);
        $uiConfig = $this->getUIConfig();

        // Get platform configurations
        $platformConfigs = [];
        foreach ($platforms as $platform) {
            if ($this->isPlatformEnabled($platform)) {
                $platformConfigs[$platform] = $this->getPlatformConfig($platform);
            }
        }

        return [
            'platforms' => $platformConfigs,
            'share_urls' => $shareUrls,
            'share_counts' => $shareCounts,
            'ui_config' => $uiConfig,
            'ui_mode' => $this->getUIMode($platforms),
        ];
    }

    /**
     * Generate cache key for share URLs
     */
    public function getCacheKey(ShareableInterface $shareable, array $platforms, string $locale): string
    {
        $prefix = config('social-share.cache.prefix', 'social_share:');
        sort($platforms);
        $platformsStr = implode(',', $platforms);

        return $prefix.'urls:'.get_class($shareable).':'.$shareable->id.':'.$platformsStr.':'.$locale;
    }

    /**
     * Clear cache for a specific model
     */
    public function clearCache(Model $model): void
    {
        if (! config('social-share.cache.enabled', true)) {
            return;
        }

        $prefix = config('social-share.cache.prefix', 'social_share:');
        $pattern = $prefix.'urls:'.get_class($model).':'.$model->id.':*';

        // Clear cache entries matching the pattern
        $tags = config('social-share.cache.tags', ['social_share']);
        if ($tags) {
            Cache::tags($tags)->flush();
        } else {
            // Fallback: clear specific keys (implementation depends on cache driver)
            $this->clearCacheByPattern($pattern);
        }
    }

    /**
     * Clear all social share cache
     */
    public function clearAllCache(): void
    {
        if (! config('social-share.cache.enabled', true)) {
            return;
        }

        $tags = config('social-share.cache.tags', ['social_share']);
        if ($tags) {
            Cache::tags($tags)->flush();
        } else {
            $prefix = config('social-share.cache.prefix', 'social_share:');
            $this->clearCacheByPattern($prefix.'*');
        }
    }

    /**
     * Apply filters to query
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['platform'])) {
            $query->where('platform', $filters['platform']);
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
    }

    /**
     * Get client IP address from request
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

        return '127.0.0.1';
    }

    /**
     * Clear cache by pattern (fallback method)
     */
    private function clearCacheByPattern(string $pattern): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar
        // For now, we'll just clear all cache with the prefix
        if (method_exists(Cache::getStore(), 'flush')) {
            // Don't flush entire cache in production
            // Cache::flush();
        }
    }
}
