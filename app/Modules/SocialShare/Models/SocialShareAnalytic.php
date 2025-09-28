<?php

namespace App\Modules\SocialShare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialShareAnalytic extends Model
{
    protected $fillable = [
        'shareable_type',
        'shareable_id',
        'platform',
        'user_id',
        'ip_address',
        'user_agent',
        'referrer',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the shareable model (Event, etc.)
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who shared (null for anonymous shares)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by platform
     */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter by authenticated users only
     */
    public function scopeAuthenticated(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Scope to filter by anonymous users only
     */
    public function scopeAnonymous(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope to filter by date range
     *
     * @param  \Carbon\Carbon  $from
     * @param  \Carbon\Carbon  $to
     */
    public function scopeDateRange(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope to filter by shareable model
     */
    public function scopeForShareable(Builder $query, Model $model): Builder
    {
        return $query->where('shareable_type', get_class($model))
            ->where('shareable_id', $model->id);
    }

    /**
     * Get total share count
     */
    public static function getTotalShares(): int
    {
        return static::count();
    }

    /**
     * Get shares grouped by platform
     */
    public static function getSharesByPlatform(): array
    {
        return static::selectRaw('platform, COUNT(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform')
            ->toArray();
    }

    /**
     * Get top platforms by share count
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getTopPlatforms(int $limit = 5)
    {
        return static::selectRaw('platform, COUNT(*) as share_count')
            ->groupBy('platform')
            ->orderByDesc('share_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if this share was from a mobile device
     */
    public function isMobile(): bool
    {
        if (! $this->user_agent) {
            return false;
        }

        $mobileKeywords = ['iPhone', 'iPad', 'Android', 'Mobile', 'Phone', 'Tablet'];

        foreach ($mobileKeywords as $keyword) {
            if (stripos($this->user_agent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get browser name from user agent
     */
    public function getBrowser(): string
    {
        if (! $this->user_agent) {
            return 'Unknown';
        }

        $browsers = [
            'Chrome' => '/Chrome\/[\d.]+/',
            'Firefox' => '/Firefox\/[\d.]+/',
            'Safari' => '/Safari\/[\d.]+/',
            'Edge' => '/Edge\/[\d.]+/',
            'Opera' => '/Opera\/[\d.]+/',
        ];

        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $this->user_agent)) {
                return $browser;
            }
        }

        return 'Unknown';
    }

    /**
     * Check if share is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Get metadata value by key
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if share came from a specific referrer domain
     */
    public function isFromReferrer(string $domain): bool
    {
        if (! $this->referrer) {
            return false;
        }

        $referrerHost = parse_url($this->referrer, PHP_URL_HOST);

        return $referrerHost === $domain || str_ends_with($referrerHost, '.'.$domain);
    }

    /**
     * Get shares for a specific model within a date range
     *
     * @param  \Carbon\Carbon  $from
     * @param  \Carbon\Carbon  $to
     */
    public static function getSharesForModel(Model $model, $from = null, $to = null): int
    {
        $query = static::forShareable($model);

        if ($from && $to) {
            $query->dateRange($from, $to);
        }

        return $query->count();
    }

    /**
     * Get popular content by share count
     *
     * @param  \Carbon\Carbon  $from
     * @param  \Carbon\Carbon  $to
     * @return \Illuminate\Support\Collection
     */
    public static function getPopularContent(string $modelType, int $limit = 10, $from = null, $to = null)
    {
        $query = static::where('shareable_type', $modelType);

        if ($from && $to) {
            $query->dateRange($from, $to);
        }

        return $query->selectRaw('shareable_id, COUNT(*) as share_count')
            ->groupBy('shareable_id')
            ->orderByDesc('share_count')
            ->limit($limit)
            ->get();
    }
}
