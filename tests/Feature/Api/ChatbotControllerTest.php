<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('validates required message field', function () {
    postJson('/api/chatbot', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

it('validates message is a string', function () {
    postJson('/api/chatbot', ['message' => 123])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

it('validates message has minimum length', function () {
    postJson('/api/chatbot', ['message' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

it('validates message has maximum length', function () {
    $longMessage = str_repeat('a', 1001);

    postJson('/api/chatbot', ['message' => $longMessage])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

it('successfully processes valid chatbot message', function () {
    config(['services.chatbot.url' => 'https://api.chatbot.test/chat']);

    Http::fake([
        '*' => Http::response([
            'message' => '**Hello!** This is a *markdown* response.',
            'timestamp' => now()->toISOString(),
        ], 200),
    ]);

    postJson('/api/chatbot', [
        'message' => 'Hello, how are you?',
    ])
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'timestamp',
        ])
        ->assertJson([
            'message' => '**Hello!** This is a *markdown* response.',
        ]);
});

it('handles remote API errors gracefully', function () {
    config(['services.chatbot.url' => 'https://api.chatbot.test/chat']);

    Http::fake([
        '*' => Http::response(null, 500),
    ]);

    postJson('/api/chatbot', [
        'message' => 'Hello',
    ])
        ->assertStatus(500)
        ->assertJson([
            'error' => 'Failed to communicate with chatbot service',
        ]);
});

it('handles remote API timeout gracefully', function () {
    config(['services.chatbot.url' => 'https://api.chatbot.test/chat']);

    Http::fake([
        '*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        },
    ]);

    postJson('/api/chatbot', [
        'message' => 'Hello',
    ])
        ->assertStatus(500)
        ->assertJson([
            'error' => 'Failed to communicate with chatbot service',
        ]);
});

it('sanitizes user message before sending to remote API', function () {
    config(['services.chatbot.url' => 'https://api.chatbot.test/chat']);

    Http::fake([
        '*' => Http::response([
            'message' => 'Response',
            'timestamp' => now()->toISOString(),
        ], 200),
    ]);

    $messageWithHtml = 'Hello <script>alert("xss")</script>';

    postJson('/api/chatbot', [
        'message' => $messageWithHtml,
    ])
        ->assertOk();

    Http::assertSent(function ($request) {
        return ! str_contains($request['message'], '<script>');
    });
});

it('forwards request to configured remote API endpoint', function () {
    config(['services.chatbot.url' => 'https://api.chatbot.example.com/chat']);
    config(['services.chatbot.key' => 'test-api-key']);

    Http::fake([
        'https://api.chatbot.example.com/chat' => Http::response([
            'message' => 'Response from chatbot',
            'timestamp' => now()->toISOString(),
        ], 200),
    ]);

    postJson('/api/chatbot', [
        'message' => 'Test message',
    ])
        ->assertOk();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.chatbot.example.com/chat'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['message'] === 'Test message';
    });
});
