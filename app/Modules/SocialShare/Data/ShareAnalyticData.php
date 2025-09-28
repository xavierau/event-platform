<?php

namespace App\Modules\SocialShare\Data;

use Spatie\LaravelData\Attributes\Validation\Integer;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class ShareAnalyticData extends Data
{
    public function __construct(
        #[Required, Rule(['string', 'max:255'])]
        public string $shareable_type,

        #[Required, Integer]
        public int $shareable_id,

        #[Required, Rule(['string', 'in:facebook,twitter,linkedin,whatsapp,telegram,wechat,weibo,email'])]
        public string $platform,

        #[Rule(['nullable', 'integer', 'exists:users,id'])]
        public ?int $user_id,

        #[Required, Rule(['ip'])]
        public string $ip_address,

        #[Rule(['nullable', 'string', 'max:500'])]
        public ?string $user_agent = null,

        #[Rule(['nullable', 'string', 'url', 'max:2048'])]
        public ?string $referrer = null,

        #[Rule(['array'])]
        public array $metadata = [],
    ) {}

    public static function rules(): array
    {
        return [
            'shareable_type' => ['required', 'string', 'max:255'],
            'shareable_id' => ['required', 'integer'],
            'platform' => ['required', 'string', 'in:facebook,twitter,linkedin,whatsapp,telegram,wechat,weibo,email'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ip_address' => ['required', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'url', 'max:2048'],
            'metadata' => ['array'],
        ];
    }

    /**
     * Check if this is a share by an authenticated user
     */
    public function isAuthenticatedShare(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Get the shareable model class name without namespace
     */
    public function getShareableModelName(): string
    {
        return class_basename($this->shareable_type);
    }

    /**
     * Determine if this is likely a mobile share based on user agent
     */
    public function isMobileShare(): bool
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
     * Get browser name from user agent
     */
    public function getBrowserName(): ?string
    {
        if (! $this->user_agent) {
            return null;
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
}
