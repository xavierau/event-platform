<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Social Media Platform Configuration
    |--------------------------------------------------------------------------
    |
    | Define the social media platforms available for sharing content.
    | Each platform has specific configuration for URL templates,
    | parameters, icons, colors, and supported features.
    |
    */

    'platforms' => [
        'facebook' => [
            'name' => 'Facebook',
            'url_template' => 'https://www.facebook.com/sharer/sharer.php?u={url}&quote={title}',
            'icon' => 'fab fa-facebook-f',
            'color' => '#1877F2',
            'supports' => ['url', 'title', 'description', 'image'],
            'parameters' => [
                'u' => 'url',
                'quote' => 'title',
            ],
            'enabled' => true,
        ],

        'instagram' => [
            'name' => 'Instagram',
            'url_template' => null, // Instagram doesn't support web sharing URLs
            'icon' => 'fab fa-instagram',
            'color' => '#E4405F',
            'supports' => ['url', 'title', 'copy_clipboard'],
            'parameters' => [],
            'copy_to_clipboard' => [
                'enabled' => true,
                'message' => 'title_and_url',
            ],
            'enabled' => false,
        ],

        'threads' => [
            'name' => 'Threads',
            'url_template' => 'https://www.threads.net/intent/post?url={url}&text={title}',
            'icon' => 'fab fa-threads',
            'color' => '#000000',
            'supports' => ['url', 'title'],
            'parameters' => [
                'url' => 'url',
                'text' => 'title',
            ],
            'enabled' => true,
        ],

        'twitter' => [
            'name' => 'X (Twitter)',
            'url_template' => 'https://twitter.com/intent/tweet?url={url}&text={title}&hashtags={hashtags}&via={via}',
            'icon' => 'fab fa-x-twitter',
            'color' => '#000000',
            'supports' => ['url', 'title', 'hashtags', 'via'],
            'parameters' => [
                'url' => 'url',
                'text' => 'title',
                'hashtags' => 'hashtags',
                'via' => 'via',
            ],
            'character_limit' => 280,
            'enabled' => false,
        ],

        'linkedin' => [
            'name' => 'LinkedIn',
            'url_template' => 'https://www.linkedin.com/feed/?shareActive=true&text={text}',
            'icon' => 'fab fa-linkedin-in',
            'color' => '#0A66C2',
            'supports' => ['url', 'title', 'description'],
            'parameters' => [
                'text' => 'description_and_url',
            ],
            'enabled' => false,
        ],

        'whatsapp' => [
            'name' => 'WhatsApp',
            'url_template' => 'https://wa.me/?text={text}',
            'icon' => 'fab fa-whatsapp',
            'color' => '#25D366',
            'supports' => ['url', 'title'],
            'parameters' => [
                'text' => 'title_and_url',
            ],
            'enabled' => true,
        ],

        'telegram' => [
            'name' => 'Telegram',
            'url_template' => 'https://t.me/share/url?url={url}&text={title}',
            'icon' => 'fab fa-telegram-plane',
            'color' => '#0088CC',
            'supports' => ['url', 'title'],
            'parameters' => [
                'url' => 'url',
                'text' => 'title',
            ],
            'enabled' => false,
        ],

        'xiaohongshu' => [
            'name' => 'Little Red Book',
            'url_template' => null, // Copy-to-clipboard sharing
            'icon' => 'fas fa-book',
            'color' => '#FF2442',
            'supports' => ['url', 'title', 'copy_clipboard'],
            'parameters' => [],
            'copy_to_clipboard' => [
                'enabled' => true,
                'message' => 'title_and_url',
            ],
            'enabled' => false,
        ],

        'wechat' => [
            'name' => 'WeChat',
            'url_template' => null, // WeChat sharing requires QR code generation
            'icon' => 'fab fa-weixin',
            'color' => '#07C160',
            'supports' => ['url', 'title', 'qr_code'],
            'parameters' => [],
            'qr_code' => [
                'enabled' => true,
                'size' => 200,
                'error_correction' => 'M',
            ],
            'enabled' => false,
        ],

        'weibo' => [
            'name' => 'Weibo',
            'url_template' => 'https://service.weibo.com/share/share.php?url={url}&title={title}&pic={image}',
            'icon' => 'fab fa-weibo',
            'color' => '#E6162D',
            'supports' => ['url', 'title', 'image'],
            'parameters' => [
                'url' => 'url',
                'title' => 'title',
                'pic' => 'image',
            ],
            'enabled' => false,
        ],

        'email' => [
            'name' => 'Email',
            'url_template' => 'mailto:?subject={title}&body={body}',
            'icon' => 'fas fa-envelope',
            'color' => '#6B7280',
            'supports' => ['url', 'title', 'description'],
            'parameters' => [
                'subject' => 'title',
                'body' => 'description_and_url',
            ],
            'enabled' => true,
        ],

        'copy_url' => [
            'name' => 'Copy URL',
            'url_template' => null, // Special handling for copying URL
            'icon' => 'fas fa-copy',
            'color' => '#6B7280',
            'supports' => ['url', 'copy_clipboard'],
            'parameters' => [],
            'copy_to_clipboard' => [
                'enabled' => true,
                'message' => 'url_only',
            ],
            'enabled' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings applied to all platforms unless overridden.
    |
    */

    'defaults' => [
        'image_size' => [
            'width' => 1200,
            'height' => 630,
        ],
        'title_length' => 60,
        'description_length' => 160,
        'popup_window' => [
            'width' => 600,
            'height' => 400,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for tracking social sharing analytics.
    |
    */

    'analytics' => [
        'enabled' => true,
        'track_anonymous' => true,
        'track_user_agent' => true,
        'track_referrer' => true,
        'retention_days' => 365, // How long to keep analytics data
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for how sharing buttons are displayed.
    |
    */

    'ui' => [
        'button_style' => 'buttons', // 'buttons' or 'dropdown'
        'show_count' => false,
        'show_labels' => true,
        'max_buttons_before_dropdown' => 4,
        'button_size' => 'medium', // 'small', 'medium', 'large'
        'border_radius' => '8px',
        'spacing' => '8px',
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for Open Graph and Twitter Card meta tags.
    |
    */

    'seo' => [
        'open_graph' => [
            'enabled' => true,
            'default_image' => '/images/default-share-image.jpg',
            'image_size' => [
                'width' => 1200,
                'height' => 630,
            ],
        ],
        'twitter_card' => [
            'enabled' => true,
            'card_type' => 'summary_large_image',
            'site' => env('TWITTER_HANDLE', '@yoursite'), // Your Twitter handle
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration for share tracking to prevent abuse.
    |
    */

    'rate_limiting' => [
        'enabled' => true,
        'max_shares_per_ip_per_hour' => 50,
        'max_shares_per_user_per_hour' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Caching settings for share counts and other data.
    |
    */

    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5 minutes
        'prefix' => 'social_share:',
        'tags' => ['social_share'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Settings for multi-language support.
    |
    */

    'localization' => [
        'enabled' => true,
        'fallback_locale' => 'en',
        'available_locales' => ['en', 'zh-TW', 'zh-CN'],
    ],
];
