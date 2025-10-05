<?php

namespace App\Modules\SocialShare\Actions;

use App\Modules\SocialShare\Contracts\ShareableInterface;
use App\Modules\SocialShare\Data\SocialPlatformData;
use InvalidArgumentException;

class GenerateShareUrlAction
{
    /**
     * Generate a share URL for the given platform data
     *
     * @throws InvalidArgumentException
     */
    public function execute(SocialPlatformData $data): ?string
    {
        $platformConfig = config("social-share.platforms.{$data->name}");

        if (! $platformConfig || ! $platformConfig['enabled']) {
            throw new InvalidArgumentException("Platform '{$data->name}' is not supported or enabled.");
        }

        // WeChat requires special handling (QR code generation)
        if ($data->name === 'wechat') {
            return null; // Will be handled by QR code generation
        }

        // Xiaohongshu requires special handling (copy-to-clipboard)
        if ($data->name === 'xiaohongshu') {
            return null; // Will be handled by copy-to-clipboard
        }

        // Copy URL requires special handling (copy-to-clipboard)
        if ($data->name === 'copy_url') {
            return null; // Will be handled by copy-to-clipboard
        }

        $template = $platformConfig['url_template'];

        // Ensure template is not null before processing
        if ($template === null) {
            return null;
        }

        $parameters = $this->buildParameters($data, $platformConfig);

        return $this->replaceTemplateVariables($template, $parameters);
    }

    /**
     * Generate share URLs for a shareable model across multiple platforms
     */
    public function generateForShareable(ShareableInterface $shareable, array $platforms, ?string $locale = null): array
    {
        $shareUrls = [];

        foreach ($platforms as $platform) {
            try {
                // Get base share URL and append UTM parameters if enabled
                $baseUrl = $shareable->getShareUrl($locale);
                $urlWithUtm = $this->appendUtmParameters($baseUrl, $platform, $shareable);

                $platformData = SocialPlatformData::from([
                    'name' => $platform,
                    'url' => $urlWithUtm,
                    'title' => $shareable->getShareTitle($locale),
                    'description' => $shareable->getShareDescription($locale),
                    'hashtags' => $shareable->getShareTags(),
                    'image_url' => $shareable->getShareImage(),
                ]);

                $shareUrl = $this->execute($platformData);
                if ($shareUrl) {
                    $shareUrls[$platform] = $shareUrl;
                }
            } catch (InvalidArgumentException $e) {
                // Skip invalid platforms
                continue;
            }
        }

        return $shareUrls;
    }

    /**
     * Build parameters array based on platform configuration
     */
    private function buildParameters(SocialPlatformData $data, array $platformConfig): array
    {
        $parameters = [];
        $parameterMappings = $platformConfig['parameters'] ?? [];

        foreach ($parameterMappings as $paramKey => $dataKey) {
            $value = $this->getParameterValue($data, $dataKey);
            if ($value !== null && $value !== '') {
                $parameters[$paramKey] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Get parameter value from platform data
     */
    private function getParameterValue(SocialPlatformData $data, string $key): ?string
    {
        switch ($key) {
            case 'url':
                return $data->url;

            case 'title':
                return $this->truncateForPlatform($data->title, $data->name, 'title');

            case 'description':
                return $this->truncateForPlatform($data->description, $data->name, 'description');

            case 'hashtags':
                return empty($data->hashtags) ? null : implode(',', $data->hashtags);

            case 'via':
                return $data->via;

            case 'image':
                return $data->image_url;

            case 'title_and_url':
                return $this->truncateForPlatform($data->title, $data->name, 'title').' '.$data->url;

            case 'description_and_url':
                $description = $this->truncateForPlatform($data->description, $data->name, 'description');

                return $description."\n\n".$data->url;

            default:
                return null;
        }
    }

    /**
     * Truncate text for platform-specific limits
     */
    private function truncateForPlatform(?string $text, string $platform, string $type): ?string
    {
        if (! $text) {
            return null;
        }

        $limits = [
            'twitter' => [
                'title' => 100, // Leave room for URL and hashtags
                'description' => 200,
            ],
            'facebook' => [
                'title' => 60,
                'description' => 160,
            ],
            'linkedin' => [
                'title' => 70,
                'description' => 200,
            ],
        ];

        $limit = $limits[$platform][$type] ?? null;

        if ($limit && strlen($text) > $limit) {
            return substr($text, 0, $limit - 3).'...';
        }

        return $text;
    }

    /**
     * Replace template variables with actual values
     */
    private function replaceTemplateVariables(string $template, array $parameters): string
    {
        $url = $template;

        foreach ($parameters as $key => $value) {
            $placeholder = '{'.$this->getPlaceholderKey($key).'}';
            // Ensure value is not null before encoding
            $encodedValue = $value !== null ? urlencode((string) $value) : '';
            $url = str_replace($placeholder, $encodedValue, $url);
        }

        // Remove any remaining empty placeholders
        $url = preg_replace('/[&?][\w]+=\{[\w]+\}/', '', $url);
        $url = preg_replace('/\{[\w]+\}/', '', $url);

        return $url;
    }

    /**
     * Get the placeholder key for template replacement
     */
    private function getPlaceholderKey(string $paramKey): string
    {
        // Map parameter keys to template placeholders
        $mappings = [
            'u' => 'url',
            'quote' => 'title',
            'text' => 'text',
            'subject' => 'title',
            'body' => 'body',
            'pic' => 'image',
            'share_text' => 'share_text',
        ];

        return $mappings[$paramKey] ?? $paramKey;
    }

    /**
     * Append UTM parameters to URL for analytics tracking
     */
    private function appendUtmParameters(string $url, string $platform, ShareableInterface $shareable): string
    {
        // Check if UTM tracking is enabled
        if (! config('social-share.analytics.utm.enabled', true)) {
            return $url;
        }

        // Parse URL to handle existing query parameters
        $parsedUrl = parse_url($url);
        $queryParams = [];

        // Extract existing query parameters
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        // Build UTM parameters
        $utmParams = $this->buildUtmParameters($platform, $shareable);

        // Merge with existing parameters (UTM params take precedence)
        $queryParams = array_merge($queryParams, $utmParams);

        // Rebuild URL with all parameters
        $baseUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];
        if (isset($parsedUrl['path'])) {
            $baseUrl .= $parsedUrl['path'];
        }

        $queryString = http_build_query($queryParams);

        return $queryString ? $baseUrl.'?'.$queryString : $baseUrl;
    }

    /**
     * Build UTM parameters for Google Analytics tracking
     */
    private function buildUtmParameters(string $platform, ShareableInterface $shareable): array
    {
        $utmMedium = config('social-share.analytics.utm.utm_medium', 'social');

        // Get campaign name from shareable model
        $utmCampaign = $shareable->getUtmCampaign();

        // Get shareable ID for content tracking
        $utmContent = $shareable instanceof \Illuminate\Database\Eloquent\Model
            ? $shareable->id
            : '';

        return [
            'utm_source' => $platform,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
        ];
    }

    /**
     * Generate QR code data for WeChat sharing
     */
    public function generateQrCodeData(SocialPlatformData $data): array
    {
        if ($data->name !== 'wechat') {
            throw new InvalidArgumentException('QR code generation is only supported for WeChat.');
        }

        $config = config('social-share.platforms.wechat.qr_code', []);

        return [
            'url' => $data->url,
            'size' => $config['size'] ?? 200,
            'error_correction' => $config['error_correction'] ?? 'M',
            'format' => 'png',
        ];
    }
}
