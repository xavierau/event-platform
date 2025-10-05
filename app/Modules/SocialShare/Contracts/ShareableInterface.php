<?php

namespace App\Modules\SocialShare\Contracts;

interface ShareableInterface
{
    /**
     * Get the title for sharing on social media platforms
     *
     * @param  string|null  $locale  The locale for the title
     */
    public function getShareTitle(?string $locale = null): string;

    /**
     * Get the description for sharing on social media platforms
     *
     * @param  string|null  $locale  The locale for the description
     */
    public function getShareDescription(?string $locale = null): string;

    /**
     * Get the URL for sharing this item
     *
     * @param  string|null  $locale  The locale for the URL
     */
    public function getShareUrl(?string $locale = null): string;

    /**
     * Get the image URL for sharing on social media platforms
     */
    public function getShareImage(): ?string;

    /**
     * Get relevant tags/hashtags for social media sharing
     */
    public function getShareTags(): array;

    /**
     * Get the UTM campaign name for analytics tracking
     */
    public function getUtmCampaign(): string;
}
