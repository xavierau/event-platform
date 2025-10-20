<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatbotMessageRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /**
     * Get messages for a session.
     */
    public function messages(ChatbotMessageRequest $request): JsonResponse
    {
        try {
            $chatbotUrl = config('services.chatbot.url');
            $chatbotKey = config('services.chatbot.key');
            $sessionId = $request->input('session_id');

            if (empty($chatbotUrl)) {
                Log::error('Chatbot API URL is not configured');

                return response()->json([
                    'error' => 'Chatbot service is not configured',
                ], 503);
            }

            $httpRequest = Http::timeout(60);

            if ($chatbotKey) {
                $httpRequest = $httpRequest->withToken($chatbotKey);
            }

            $response = $httpRequest->post("{$chatbotUrl}/messages", [
                'session_id' => $sessionId,
            ]);

            Log::info('messages: ', [$response->json()]);

            if ($response->failed()) {
                Log::error('Failed to fetch chatbot messages', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Failed to fetch messages',
                ], 500);
            }

            return response()->json($response->json());
        } catch (ConnectionException $e) {
            Log::error('Chatbot API connection failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to communicate with chatbot service',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Chatbot API unexpected error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to communicate with chatbot service',
            ], 500);
        }
    }

    /**
     * Process chatbot message and return response.
     */
    public function store(ChatbotMessageRequest $request): JsonResponse
    {
        try {
            $sanitizedMessage = $request->sanitizedMessage();
            $chatbotUrl = config('services.chatbot.url');
            $chatbotKey = config('services.chatbot.key');
            $sessionId = $request->input('session_id');

            Log::info('Chatbot message received:', [$sanitizedMessage]);
            Log::info('Chatbot URL: ', [$chatbotUrl]);
            // Validate that chatbot URL is configured
            if (empty($chatbotUrl)) {
                Log::error('Chatbot API URL is not configured');

                return response()->json([
                    'error' => 'Chatbot service is not configured',
                ], 503);
            }

            $httpRequest = Http::timeout(60);

            if ($chatbotKey) {
                $httpRequest = $httpRequest->withToken($chatbotKey);
            }

            $response = $httpRequest->post($chatbotUrl, [
                'user_input' => $sanitizedMessage,
                'user_id' => $request->user()?->id,
                'session_id' => $sessionId,
                'current_url' => $request->input('current_url'),
                'page_content' => $request->input('page_content'),
            ]);

            Log::info('Response from server,', [$response]);

            if ($response->failed()) {
                Log::error('Chatbot API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Failed to communicate with chatbot service',
                ], 500);
            }

            return response()->json($response->json());
        } catch (ConnectionException $e) {
            Log::error('Chatbot API connection failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to communicate with chatbot service',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Chatbot API unexpected error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to communicate with chatbot service',
            ], 500);
        }
    }
}
