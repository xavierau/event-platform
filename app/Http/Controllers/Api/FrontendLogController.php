<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class FrontendLogController extends Controller
{
    /**
     * Store frontend logs
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'logs' => 'required|array|max:100',
            'logs.*.level' => 'required|string|in:debug,info,warn,error',
            'logs.*.component' => 'required|string|max:100',
            'logs.*.message' => 'required|string|max:1000',
            'logs.*.data' => 'nullable|array',
            'logs.*.timestamp' => 'required|string',
            'logs.*.url' => 'required|string|max:500',
            'logs.*.userAgent' => 'required|string|max:500',
        ]);

        $user = Auth::user();
        $logs = $request->input('logs');

        foreach ($logs as $logEntry) {
            $context = [
                'frontend' => true,
                'component' => $logEntry['component'],
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'client_timestamp' => $logEntry['timestamp'],
                'url' => $logEntry['url'],
                'user_agent' => $logEntry['userAgent'],
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),
            ];

            // Add the data if present
            if (isset($logEntry['data'])) {
                $context['data'] = $logEntry['data'];
            }

            // Log based on level using dedicated frontend channel
            switch ($logEntry['level']) {
                case 'error':
                    Log::channel('frontend')->error('[FRONTEND] ' . $logEntry['message'], $context);
                    break;
                case 'warn':
                    Log::channel('frontend')->warning('[FRONTEND] ' . $logEntry['message'], $context);
                    break;
                case 'info':
                    Log::channel('frontend')->info('[FRONTEND] ' . $logEntry['message'], $context);
                    break;
                case 'debug':
                    Log::channel('frontend')->debug('[FRONTEND] ' . $logEntry['message'], $context);
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Logs stored successfully',
            'count' => count($logs)
        ]);
    }
}