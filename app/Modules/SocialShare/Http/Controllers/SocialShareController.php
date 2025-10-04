<?php

namespace App\Modules\SocialShare\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SocialShare\Contracts\ShareableInterface;
use App\Modules\SocialShare\Services\SocialShareService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialShareController extends Controller
{
    public function __construct(
        private SocialShareService $socialShareService
    ) {}

    /**
     * Generate share URLs for a shareable model
     */
    public function urls(Request $request): JsonResponse
    {
        $request->validate([
            'shareable_type' => 'required|string',
            'shareable_id' => 'required|integer',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string',
            'locale' => 'nullable|string|max:10',
        ]);

        try {
            $model = $this->findShareableModel(
                $request->input('shareable_type'),
                $request->input('shareable_id')
            );

            $platforms = $request->input('platforms');
            $locale = $request->input('locale', app()->getLocale());

            $shareData = $this->socialShareService->getShareButtonData($model, $platforms, $locale);

            return response()->json([
                'data' => $shareData,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Model not found',
                'message' => 'The specified shareable model could not be found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Share URL generation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Track a share action
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'shareable_type' => 'required|string',
            'shareable_id' => 'required|integer',
            'platform' => 'required|string|in:facebook,twitter,linkedin,whatsapp,telegram,wechat,weibo,email,xiaohongshu,copy_url,instagram,threads',
            'metadata' => 'nullable|array',
        ]);

        try {
            $model = $this->findShareableModel(
                $request->input('shareable_type'),
                $request->input('shareable_id')
            );

            $user = $request->user();

            $analytic = $this->socialShareService->trackShareFromRequest($model, $request, $user);

            return response()->json([
                'data' => [
                    'id' => $analytic->id,
                    'platform' => $analytic->platform,
                    'shareable_type' => $analytic->shareable_type,
                    'shareable_id' => $analytic->shareable_id,
                    'user_id' => $analytic->user_id,
                    'created_at' => $analytic->created_at,
                ],
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Model not found',
                'message' => 'The specified shareable model could not be found.',
            ], 404);
        } catch (\Exception $e) {
            // Check if it's a rate limiting exception
            if (str_contains($e->getMessage(), 'Rate limit exceeded')) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => 'Too many share requests. Please try again later.',
                ], 429);
            }

            return response()->json([
                'error' => 'Share tracking failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get share analytics for a model or globally
     */
    public function analytics(Request $request): JsonResponse
    {
        $request->validate([
            'shareable_type' => 'nullable|string',
            'shareable_id' => 'nullable|integer',
            'platform' => 'nullable|string|in:facebook,twitter,linkedin,whatsapp,telegram,wechat,weibo,email,xiaohongshu,copy_url,instagram,threads',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'authenticated_only' => 'nullable|boolean',
            'anonymous_only' => 'nullable|boolean',
        ]);

        try {
            $model = null;

            // If model parameters are provided, find the model
            if ($request->filled('shareable_type') && $request->filled('shareable_id')) {
                $model = $this->findShareableModel(
                    $request->input('shareable_type'),
                    $request->input('shareable_id')
                );
            }

            $filters = array_filter([
                'platform' => $request->input('platform'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'authenticated_only' => $request->input('authenticated_only'),
                'anonymous_only' => $request->input('anonymous_only'),
            ]);

            $statistics = $this->socialShareService->getShareStatistics($model, $filters);

            return response()->json([
                'data' => $statistics,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Model not found',
                'message' => 'The specified shareable model could not be found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Analytics retrieval failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get popular content by share count
     */
    public function popular(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        try {
            $modelType = $request->input('model_type');
            $limit = $request->input('limit', 10);

            $filters = array_filter([
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ]);

            $popularContent = $this->socialShareService->getPopularContent($modelType, $limit, $filters);

            return response()->json([
                'data' => $popularContent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Popular content retrieval failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get platform configuration
     */
    public function platforms(Request $request): JsonResponse
    {
        try {
            $enabledPlatforms = $this->socialShareService->getEnabledPlatforms();
            $platformConfigs = [];

            foreach ($enabledPlatforms as $platform) {
                $config = $this->socialShareService->getPlatformConfig($platform);
                if ($config) {
                    // Remove sensitive configuration like URL templates
                    $platformConfigs[$platform] = [
                        'name' => $config['name'],
                        'icon' => $config['icon'],
                        'color' => $config['color'],
                        'supports' => $config['supports'] ?? [],
                    ];
                }
            }

            $uiConfig = $this->socialShareService->getUIConfig();

            return response()->json([
                'data' => [
                    'platforms' => $platformConfigs,
                    'ui_config' => $uiConfig,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Platform configuration retrieval failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear cache for a specific model
     */
    public function clearCache(Request $request): JsonResponse
    {
        $request->validate([
            'shareable_type' => 'nullable|string',
            'shareable_id' => 'nullable|integer',
            'clear_all' => 'nullable|boolean',
        ]);

        try {
            if ($request->input('clear_all')) {
                $this->socialShareService->clearAllCache();
                $message = 'All social share cache cleared successfully.';
            } else {
                $model = $this->findShareableModel(
                    $request->input('shareable_type'),
                    $request->input('shareable_id')
                );

                $this->socialShareService->clearCache($model);
                $message = 'Cache cleared successfully for the specified model.';
            }

            return response()->json([
                'message' => $message,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Model not found',
                'message' => 'The specified shareable model could not be found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Cache clearing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find a shareable model by type and ID
     *
     * @throws ModelNotFoundException
     */
    private function findShareableModel(string $type, int $id): ShareableInterface
    {
        // Security: Only allow specific model types
        $allowedModels = [
            'App\\Models\\Event' => \App\Models\Event::class,
            // Add other shareable models here as needed
        ];

        if (! isset($allowedModels[$type])) {
            throw new ModelNotFoundException("Model type '{$type}' is not supported for sharing.");
        }

        $modelClass = $allowedModels[$type];

        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model not found with ID {$id}.");
        }

        if (! $model instanceof ShareableInterface) {
            throw new \InvalidArgumentException('Model must implement ShareableInterface.');
        }

        return $model;
    }
}
