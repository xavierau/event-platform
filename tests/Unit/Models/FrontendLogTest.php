<?php

use App\Models\FrontendLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('FrontendLog Model', function () {
    it('can create a frontend log entry', function () {
        $log = FrontendLog::create([
            'level' => 'info',
            'component' => 'Registration',
            'message' => 'Test log message',
            'data' => ['key' => 'value'],
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'ip_address' => '192.168.1.1',
            'session_id' => 'test-session-123',
        ]);

        expect($log->level)->toBe('info');
        expect($log->component)->toBe('Registration');
        expect($log->message)->toBe('Test log message');
        expect($log->data)->toBe(['key' => 'value']);
        expect($log->url)->toBe('http://localhost/test');
        expect($log->ip_address)->toBe('192.168.1.1');
        expect($log->session_id)->toBe('test-session-123');
    });

    it('casts data as array', function () {
        $log = FrontendLog::create([
            'level' => 'error',
            'component' => 'Registration',
            'message' => 'Error message',
            'data' => ['error_code' => 500, 'details' => ['field' => 'required']],
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        expect($log->data)->toBeArray();
        expect($log->data['error_code'])->toBe(500);
        expect($log->data['details']['field'])->toBe('required');
    });

    it('casts client_timestamp as datetime', function () {
        $timestamp = now();
        $log = FrontendLog::create([
            'level' => 'debug',
            'component' => 'Test',
            'message' => 'Debug message',
            'client_timestamp' => $timestamp,
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        expect($log->client_timestamp)->toBeInstanceOf(Carbon\Carbon::class);
        expect($log->client_timestamp->toDateTimeString())->toBe($timestamp->toDateTimeString());
    });

    it('belongs to user when user_id is set', function () {
        $user = User::factory()->create();
        $log = FrontendLog::create([
            'level' => 'info',
            'component' => 'Registration',
            'message' => 'User action',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'user_id' => $user->id,
        ]);

        expect($log->user)->toBeInstanceOf(User::class);
        expect($log->user->id)->toBe($user->id);
    });

    it('filters by log level using scope', function () {
        FrontendLog::create([
            'level' => 'info',
            'component' => 'Test',
            'message' => 'Info message',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        FrontendLog::create([
            'level' => 'error',
            'component' => 'Test',
            'message' => 'Error message',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        $infoLogs = FrontendLog::byLevel('info')->get();
        $errorLogs = FrontendLog::errors()->get();

        expect($infoLogs)->toHaveCount(1);
        expect($errorLogs)->toHaveCount(1);
        expect($infoLogs->first()->level)->toBe('info');
        expect($errorLogs->first()->level)->toBe('error');
    });

    it('filters by component using scope', function () {
        FrontendLog::create([
            'level' => 'info',
            'component' => 'Registration',
            'message' => 'Registration message',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        FrontendLog::create([
            'level' => 'info',
            'component' => 'QRScanner',
            'message' => 'Scanner message',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        $registrationLogs = FrontendLog::byComponent('Registration')->get();
        $scannerLogs = FrontendLog::byComponent('QRScanner')->get();

        expect($registrationLogs)->toHaveCount(1);
        expect($scannerLogs)->toHaveCount(1);
        expect($registrationLogs->first()->component)->toBe('Registration');
        expect($scannerLogs->first()->component)->toBe('QRScanner');
    });

    it('filters recent logs using scope', function () {
        // Create an old log (25 hours ago)
        FrontendLog::create([
            'level' => 'info',
            'component' => 'Test',
            'message' => 'Old message',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'created_at' => now()->subHours(25),
        ]);

        // Create a recent log
        $recentLog = FrontendLog::create([
            'level' => 'info',
            'component' => 'Test',
            'message' => 'Recent message',
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ]);

        $recentLogs = FrontendLog::recent(24)->get();

        expect($recentLogs)->toHaveCount(1);
        expect($recentLogs->first()->id)->toBe($recentLog->id);
    });

    it('handles null values gracefully', function () {
        $log = FrontendLog::create([
            'level' => 'warn',
            'component' => 'Test',
            'message' => 'Warning message',
            'data' => null,
            'client_timestamp' => now(),
            'url' => 'http://localhost/test',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'ip_address' => null,
            'session_id' => null,
            'user_id' => null,
        ]);

        expect($log->data)->toBeNull();
        expect($log->ip_address)->toBeNull();
        expect($log->session_id)->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->user)->toBeNull();
    });
});