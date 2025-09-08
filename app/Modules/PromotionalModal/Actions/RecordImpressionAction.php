<?php

namespace App\Modules\PromotionalModal\Actions;

use App\Models\User;
use App\Modules\PromotionalModal\Models\PromotionalModal;
use App\Modules\PromotionalModal\Models\PromotionalModalImpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordImpressionAction
{
    /**
     * Record an impression, click, or dismissal for a promotional modal.
     */
    public function execute(
        PromotionalModal $promotionalModal,
        string $action = 'impression',
        ?User $user = null,
        ?string $sessionId = null,
        ?string $pageUrl = null,
        ?array $metadata = null,
        ?Request $request = null
    ): PromotionalModalImpression {
        return DB::transaction(function () use ($promotionalModal, $action, $user, $sessionId, $pageUrl, $metadata, $request) {
            // Create impression record
            $impressionData = [
                'promotional_modal_id' => $promotionalModal->id,
                'user_id' => $user?->id,
                'session_id' => $sessionId,
                'action' => $action,
                'page_url' => $pageUrl,
                'metadata' => $metadata,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => now(),
            ];

            $impression = PromotionalModalImpression::create($impressionData);

            // Update modal statistics
            if ($action === 'impression') {
                $promotionalModal->incrementImpressions();
            } elseif ($action === 'click') {
                $promotionalModal->incrementClicks();
            }

            return $impression;
        });
    }

    /**
     * Record multiple impressions at once (for bulk operations).
     */
    public function executeBulk(array $impressionData): void
    {
        DB::transaction(function () use ($impressionData) {
            // Prepare data for bulk insert
            $impressions = collect($impressionData)->map(function ($data) {
                return [
                    'promotional_modal_id' => $data['promotional_modal_id'],
                    'user_id' => $data['user_id'] ?? null,
                    'session_id' => $data['session_id'] ?? null,
                    'action' => $data['action'] ?? 'impression',
                    'page_url' => $data['page_url'] ?? null,
                    'metadata' => $data['metadata'] ? json_encode($data['metadata']) : null,
                    'ip_address' => $data['ip_address'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                    'created_at' => $data['created_at'] ?? now(),
                ];
            })->toArray();

            PromotionalModalImpression::insert($impressions);

            // Update statistics for affected modals
            $modalIds = collect($impressionData)->pluck('promotional_modal_id')->unique();
            
            foreach ($modalIds as $modalId) {
                $modal = PromotionalModal::find($modalId);
                if ($modal) {
                    $impressionCount = collect($impressionData)
                        ->where('promotional_modal_id', $modalId)
                        ->where('action', 'impression')
                        ->count();
                    
                    $clickCount = collect($impressionData)
                        ->where('promotional_modal_id', $modalId)
                        ->where('action', 'click')
                        ->count();

                    if ($impressionCount > 0) {
                        $modal->increment('impressions_count', $impressionCount);
                    }
                    
                    if ($clickCount > 0) {
                        $modal->increment('clicks_count', $clickCount);
                    }

                    // Recalculate conversion rate
                    $modal->refresh();
                    if ($modal->impressions_count > 0) {
                        $rate = ($modal->clicks_count / $modal->impressions_count) * 100;
                        $modal->update(['conversion_rate' => round($rate, 2)]);
                    }
                }
            }
        });
    }
}