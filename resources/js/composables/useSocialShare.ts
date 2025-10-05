import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useGoogleAnalytics } from './useGoogleAnalytics';

interface ShareConfig {
    url: string;
    title: string;
    description: string;
    image?: string;
    hashtags?: string[];
}

interface PlatformConfig {
    key: string;
    name: string;
    icon: string;
    color: string;
    enabled: boolean;
    sort_order: number;
}

interface ShareResponse {
    success: boolean;
    data?: {
        platforms: PlatformConfig[];
        share_urls: Record<string, string>;
        share_counts: Record<string, number>;
        ui_config: {
            button_threshold: number;
            display_mode: string;
            show_share_count: boolean;
            track_analytics: boolean;
        };
    };
    message?: string;
}

export function useSocialShare() {
    const platforms = ref<PlatformConfig[]>([]);
    const shareUrls = ref<Record<string, string>>({});
    const shareCounts = ref<Record<string, number>>({});
    const uiConfig = ref({
        button_threshold: 2,
        display_mode: 'auto',
        show_share_count: true,
        track_analytics: true,
    });
    const isLoading = ref(false);
    const isSharing = ref(false);
    const shareSuccess = ref(false);

    // Initialize Google Analytics tracking
    const { trackEvent } = useGoogleAnalytics();

    const displayMode = computed(() => {
        if (uiConfig.value.display_mode !== 'auto') {
            return uiConfig.value.display_mode;
        }

        const platformCount = platforms.value.length;

        if (platformCount <= uiConfig.value.button_threshold) {
            return 'buttons';
        }

        return 'dropdown';
    });

    const loadShareData = async (shareableType: string, shareableId: number) => {
        isLoading.value = true;
        try {
            // Load share URLs and all data - this endpoint returns everything we need
            const urlsResponse = await fetch(`/api/social-share/urls?shareable_type=${encodeURIComponent(shareableType)}&shareable_id=${shareableId}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });
            const urlsData = await urlsResponse.json();

            if (urlsData.data) {
                // Load share URLs
                if (urlsData.data.share_urls) {
                    shareUrls.value = urlsData.data.share_urls;
                }

                // Load share counts
                if (urlsData.data.share_counts) {
                    shareCounts.value = urlsData.data.share_counts;
                }

                // Load platform configurations
                if (urlsData.data.platforms) {
                    // Convert platforms object to array format expected by components
                    // Include ALL enabled platforms, not just those with share URLs
                    platforms.value = Object.entries(urlsData.data.platforms)
                        .filter(([key, platform]: [string, any]) => platform.enabled !== false)
                        .map(([key, platform]: [string, any]) => ({
                            key,
                            name: platform.name,
                            icon: platform.icon,
                            color: platform.color,
                            enabled: platform.enabled ?? true,
                            sort_order: 1,
                        }));
                }

                // Load UI configuration
                if (urlsData.data.ui_config) {
                    uiConfig.value = { ...uiConfig.value, ...urlsData.data.ui_config };
                }
            }
        } catch (error) {
            console.error('Failed to load share data:', error);
        } finally {
            isLoading.value = false;
        }
    };

    const shareToFacebook = (url: string) => {
        window.open(url, '_blank', 'width=600,height=400');
    };

    const shareToTwitter = (url: string) => {
        window.open(url, '_blank', 'width=600,height=400');
    };

    const shareToLinkedIn = (url: string) => {
        window.open(url, '_blank', 'width=600,height=400');
    };

    const shareToWhatsApp = (url: string) => {
        window.open(url, '_blank', 'width=600,height=400');
    };

    const shareToEmail = (url: string) => {
        window.location.href = url;
    };

    const generateWeChatQR = async (url: string): Promise<string> => {
        // For now, return the URL. In a real implementation, you'd generate a QR code
        return `data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><text x="10" y="20">QR Code for: ${encodeURIComponent(url)}</text></svg>`;
    };

    const shareToPlatform = async (platform: string, shareableType: string, shareableId: number) => {
        isSharing.value = true;
        shareSuccess.value = false;

        try {
            const url = shareUrls.value[platform];

            // Some platforms (copy_url, xiaohongshu, wechat, instagram) don't have traditional URLs
            // but are handled specially below
            if (!url && !['copy_url', 'xiaohongshu', 'wechat', 'instagram'].includes(platform)) {
                throw new Error(`No share URL found for platform: ${platform}`);
            }

            // Handle platform-specific sharing
            switch (platform) {
                case 'facebook':
                    shareToFacebook(url);
                    break;
                case 'x':
                case 'twitter':
                    shareToTwitter(url);
                    break;
                case 'linkedin':
                    shareToLinkedIn(url);
                    break;
                case 'whatsapp':
                    shareToWhatsApp(url);
                    break;
                case 'email':
                    shareToEmail(url);
                    break;
                case 'wechat':
                    // Show QR code modal instead of opening URL - get the original URL from any other platform
                    const wechatUrl = Object.values(shareUrls.value)[0]?.match(/u=([^&]+)|url=([^&]+)/)?.[1] || Object.values(shareUrls.value)[0]?.match(/u=([^&]+)|url=([^&]+)/)?.[2] || window.location.href;
                    const decodedWechatUrl = wechatUrl ? decodeURIComponent(wechatUrl) : window.location.href;
                    const qrCode = await generateWeChatQR(decodedWechatUrl);
                    showWeChatModal(qrCode);
                    break;
                case 'xiaohongshu':
                    // Copy content to clipboard for sharing in Xiaohongshu
                    await copyToClipboardForXiaohongshu(shareableType, shareableId);
                    break;
                case 'copy_url':
                    // Copy the URL to clipboard - get the original URL from any other platform since copy_url doesn't generate URLs
                    const originalUrl = Object.values(shareUrls.value)[0]?.match(/u=([^&]+)|url=([^&]+)/)?.[1] || Object.values(shareUrls.value)[0]?.match(/u=([^&]+)|url=([^&]+)/)?.[2] || window.location.href;
                    const decodedUrl = originalUrl ? decodeURIComponent(originalUrl) : window.location.href;
                    await copyUrlToClipboard(decodedUrl);
                    break;
                case 'instagram':
                    // Copy content to clipboard for sharing in Instagram
                    await copyToClipboardForInstagram(shareableType, shareableId);
                    break;
                case 'threads':
                    // Open Threads sharing URL
                    window.open(url, '_blank', 'width=600,height=400');
                    break;
                default:
                    window.open(url, '_blank', 'width=600,height=400');
            }

            // Track the share if analytics is enabled
            if (uiConfig.value.track_analytics) {
                await trackShare(shareableType, shareableId, platform);

                // Track share event in Google Analytics
                trackEvent('share', {
                    method: platform,
                    content_type: shareableType,
                    item_id: shareableId,
                });
            }

            shareSuccess.value = true;
            setTimeout(() => {
                shareSuccess.value = false;
            }, 3000);

        } catch (error) {
            console.error('Failed to share:', error);
        } finally {
            isSharing.value = false;
        }
    };

    const trackShare = async (shareableType: string, shareableId: number, platform: string, metadata: Record<string, any> = {}) => {
        try {
            await fetch('/api/social-share/track', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    shareable_type: shareableType,
                    shareable_id: shareableId,
                    platform: platform,
                    metadata: metadata,
                }),
            });
        } catch (error) {
            console.error('Failed to track share:', error);
        }
    };

    const showWeChatModal = (qrCode: string) => {
        // This would show a modal with the QR code
        // For now, we'll just log it
        console.log('WeChat QR Code:', qrCode);
        alert('WeChat sharing: Please scan the QR code (implementation pending)');
    };

    const copyToClipboardForXiaohongshu = async (shareableType: string, shareableId: number) => {
        try {
            // Get the event data first
            const response = await fetch(`/api/social-share/urls?shareable_type=${encodeURIComponent(shareableType)}&shareable_id=${shareableId}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();

            if (data.data && data.data.platforms && data.data.platforms.xiaohongshu) {
                const platformConfig = data.data.platforms.xiaohongshu;

                // Create the share message manually since we don't have a URL
                const title = data.data.platforms.facebook ?
                    decodeURIComponent(data.data.share_urls.facebook.match(/quote=([^&]+)/)?.[1] || '') :
                    'Check out this event!';

                const eventUrl = data.data.share_urls.facebook ?
                    decodeURIComponent(data.data.share_urls.facebook.match(/u=([^&]+)/)?.[1] || '') :
                    window.location.href;

                const shareText = `${title}\n\n${eventUrl}\n\n#Event #EventPlatform`;

                // Copy to clipboard
                await navigator.clipboard.writeText(shareText);

                // Show success message
                alert('Content copied to clipboard! You can now paste it in Xiaohongshu (Little Red Book).');
            } else {
                throw new Error('Failed to get sharing data');
            }
        } catch (error) {
            console.error('Failed to copy for Xiaohongshu:', error);
            alert('Failed to copy content. Please try again.');
        }
    };

    const copyToClipboardForInstagram = async (shareableType: string, shareableId: number) => {
        try {
            // Get the event data first
            const response = await fetch(`/api/social-share/urls?shareable_type=${encodeURIComponent(shareableType)}&shareable_id=${shareableId}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();

            if (data.data && data.data.platforms) {
                // Create the share message for Instagram
                const title = data.data.platforms.facebook ?
                    decodeURIComponent(data.data.share_urls.facebook.match(/quote=([^&]+)/)?.[1] || '') :
                    'Check out this event!';

                const eventUrl = data.data.share_urls.facebook ?
                    decodeURIComponent(data.data.share_urls.facebook.match(/u=([^&]+)/)?.[1] || '') :
                    window.location.href;

                const shareText = `${title}\n\n${eventUrl}\n\n#Event #EventPlatform #Instagram`;

                // Copy to clipboard
                await navigator.clipboard.writeText(shareText);

                // Show success message
                alert('Content copied to clipboard! You can now paste it in Instagram.');
            } else {
                throw new Error('Failed to get sharing data');
            }
        } catch (error) {
            console.error('Failed to copy for Instagram:', error);
            alert('Failed to copy content. Please try again.');
        }
    };

    const copyUrlToClipboard = async (url: string) => {
        try {
            await navigator.clipboard.writeText(url);
            alert('URL copied to clipboard!');
        } catch (error) {
            console.error('Failed to copy URL:', error);
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = url;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('URL copied to clipboard!');
        }
    };

    return {
        platforms,
        shareUrls,
        shareCounts,
        uiConfig,
        displayMode,
        isLoading,
        isSharing,
        shareSuccess,
        loadShareData,
        shareToPlatform,
        trackShare,
        shareToFacebook,
        shareToTwitter,
        shareToLinkedIn,
        shareToWhatsApp,
        shareToEmail,
        generateWeChatQR,
    };
}