<?php

namespace App\Modules\SocialShare\Contracts;

use App\Modules\SocialShare\Data\SocialPlatformData;

interface PlatformShareInterface
{
    /**
     * Get the platform name
     */
    public function getName(): string;

    /**
     * Generate a share URL for this platform
     */
    public function generateShareUrl(SocialPlatformData $data): string;

    /**
     * Get the platform icon name/class
     */
    public function getIcon(): string;

    /**
     * Get the platform brand color
     */
    public function getColor(): string;

    /**
     * Check if this platform supports the given features
     */
    public function supports(array $features): bool;
}
