<?php

use App\Models\RegistrationAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RegistrationAuditLog Model', function () {
    it('can create a registration audit log entry', function () {
        $flowId = RegistrationAuditLog::generateFlowId();
        $log = RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'form_submission',
            'action' => 'form_submitted',
            'status' => 'success',
            'message' => 'Test audit log message',
            'request_data' => ['name' => 'Test User', 'email' => 'test@example.com'],
            'response_data' => ['user_id' => 123],
            'metadata' => ['ip_address' => '192.168.1.1'],
            'email' => 'test@example.com',
            'selected_plan' => 'price_premium_monthly',
            'error_message' => null,
            'stripe_session_id' => 'sess_test123',
        ]);

        expect($log->flow_id)->toBe($flowId);
        expect($log->step)->toBe('form_submission');
        expect($log->action)->toBe('form_submitted');
        expect($log->status)->toBe('success');
        expect($log->message)->toBe('Test audit log message');
        expect($log->email)->toBe('test@example.com');
        expect($log->selected_plan)->toBe('price_premium_monthly');
        expect($log->stripe_session_id)->toBe('sess_test123');
    });

    it('generates unique flow IDs', function () {
        $flowId1 = RegistrationAuditLog::generateFlowId();
        $flowId2 = RegistrationAuditLog::generateFlowId();

        expect($flowId1)->not->toBe($flowId2);
        expect($flowId1)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        expect($flowId2)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('casts arrays correctly', function () {
        $requestData = ['name' => 'Test User', 'email' => 'test@example.com'];
        $responseData = ['user_id' => 123, 'created_at' => '2023-01-01T12:00:00Z'];
        $metadata = ['ip_address' => '192.168.1.1', 'user_agent' => 'Mozilla/5.0'];

        $log = RegistrationAuditLog::create([
            'flow_id' => RegistrationAuditLog::generateFlowId(),
            'step' => 'test',
            'action' => 'test_action',
            'status' => 'success',
            'message' => 'Test message',
            'request_data' => $requestData,
            'response_data' => $responseData,
            'metadata' => $metadata,
        ]);

        expect($log->request_data)->toBeArray();
        expect($log->response_data)->toBeArray();
        expect($log->metadata)->toBeArray();
        expect($log->request_data['name'])->toBe('Test User');
        expect($log->response_data['user_id'])->toBe(123);
        expect($log->metadata['ip_address'])->toBe('192.168.1.1');
    });

    it('belongs to user when user_id is set', function () {
        $user = User::factory()->create();
        $log = RegistrationAuditLog::create([
            'flow_id' => RegistrationAuditLog::generateFlowId(),
            'step' => 'user_creation',
            'action' => 'user_created',
            'status' => 'success',
            'message' => 'User created successfully',
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        expect($log->user)->toBeInstanceOf(User::class);
        expect($log->user->id)->toBe($user->id);
    });

    it('sanitizes sensitive data in logStep method', function () {
        $sensitiveData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'credit_card_number' => '4242424242424242',
            'api_key' => 'secret_key_123',
            'stripe_secret' => 'sk_test_secret',
            'nested' => [
                'password' => 'nested_secret',
                'safe_field' => 'safe_value',
            ],
        ];

        $log = RegistrationAuditLog::logStep([
            'flow_id' => RegistrationAuditLog::generateFlowId(),
            'step' => 'test_sanitization',
            'action' => 'test_data_sanitization',
            'status' => 'success',
            'message' => 'Testing data sanitization',
            'request_data' => $sensitiveData,
            'response_data' => $sensitiveData,
        ]);

        // Check that sensitive fields are redacted
        expect($log->request_data['password'])->toBe('[REDACTED]');
        expect($log->request_data['password_confirmation'])->toBe('[REDACTED]');
        expect($log->request_data['credit_card_number'])->toBe('[REDACTED]');
        expect($log->request_data['api_key'])->toBe('[REDACTED]');
        expect($log->request_data['stripe_secret'])->toBe('[REDACTED]');
        expect($log->request_data['nested']['password'])->toBe('[REDACTED]');

        // Check that safe fields are preserved
        expect($log->request_data['name'])->toBe('Test User');
        expect($log->request_data['email'])->toBe('test@example.com');
        expect($log->request_data['nested']['safe_field'])->toBe('safe_value');

        // Same checks for response_data
        expect($log->response_data['password'])->toBe('[REDACTED]');
        expect($log->response_data['name'])->toBe('Test User');
    });

    it('filters by flow ID using scope', function () {
        $flowId1 = RegistrationAuditLog::generateFlowId();
        $flowId2 = RegistrationAuditLog::generateFlowId();

        RegistrationAuditLog::create([
            'flow_id' => $flowId1,
            'step' => 'step1',
            'action' => 'action1',
            'status' => 'success',
            'message' => 'Message 1',
        ]);

        RegistrationAuditLog::create([
            'flow_id' => $flowId1,
            'step' => 'step2',
            'action' => 'action2',
            'status' => 'success',
            'message' => 'Message 2',
        ]);

        RegistrationAuditLog::create([
            'flow_id' => $flowId2,
            'step' => 'step1',
            'action' => 'action1',
            'status' => 'success',
            'message' => 'Message 3',
        ]);

        $flow1Logs = RegistrationAuditLog::byFlow($flowId1)->get();
        $flow2Logs = RegistrationAuditLog::byFlow($flowId2)->get();

        expect($flow1Logs)->toHaveCount(2);
        expect($flow2Logs)->toHaveCount(1);
        expect($flow1Logs->pluck('message'))->toContain('Message 1', 'Message 2');
        expect($flow2Logs->first()->message)->toBe('Message 3');
    });

    it('filters by step using scope', function () {
        $flowId = RegistrationAuditLog::generateFlowId();

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'form_submission',
            'action' => 'form_submitted',
            'status' => 'success',
            'message' => 'Form submitted',
        ]);

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'form_validation',
            'action' => 'validation_passed',
            'status' => 'success',
            'message' => 'Validation passed',
        ]);

        $submissionLogs = RegistrationAuditLog::byStep('form_submission')->get();
        $validationLogs = RegistrationAuditLog::byStep('form_validation')->get();

        expect($submissionLogs)->toHaveCount(1);
        expect($validationLogs)->toHaveCount(1);
        expect($submissionLogs->first()->message)->toBe('Form submitted');
        expect($validationLogs->first()->message)->toBe('Validation passed');
    });

    it('filters by status using scopes', function () {
        $flowId = RegistrationAuditLog::generateFlowId();

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'step1',
            'action' => 'action1',
            'status' => 'success',
            'message' => 'Success message',
        ]);

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'step2',
            'action' => 'action2',
            'status' => 'failed',
            'message' => 'Failure message',
        ]);

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'step3',
            'action' => 'action3',
            'status' => 'pending',
            'message' => 'Pending message',
        ]);

        $successLogs = RegistrationAuditLog::successful()->get();
        $failedLogs = RegistrationAuditLog::failed()->get();

        expect($successLogs)->toHaveCount(1);
        expect($failedLogs)->toHaveCount(1);
        expect($successLogs->first()->message)->toBe('Success message');
        expect($failedLogs->first()->message)->toBe('Failure message');
    });

    it('filters by email using scope', function () {
        $flowId = RegistrationAuditLog::generateFlowId();

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'step1',
            'action' => 'action1',
            'status' => 'success',
            'message' => 'Message 1',
            'email' => 'user1@example.com',
        ]);

        RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'step2',
            'action' => 'action2',
            'status' => 'success',
            'message' => 'Message 2',
            'email' => 'user2@example.com',
        ]);

        $user1Logs = RegistrationAuditLog::byEmail('user1@example.com')->get();
        $user2Logs = RegistrationAuditLog::byEmail('user2@example.com')->get();

        expect($user1Logs)->toHaveCount(1);
        expect($user2Logs)->toHaveCount(1);
        expect($user1Logs->first()->message)->toBe('Message 1');
        expect($user2Logs->first()->message)->toBe('Message 2');
    });

    it('filters recent logs using scope', function () {
        $flowId = RegistrationAuditLog::generateFlowId();

        // Create an old log
        $oldLog = RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'old_step',
            'action' => 'old_action',
            'status' => 'success',
            'message' => 'Old message',
        ]);
        $oldLog->created_at = now()->subHours(25);
        $oldLog->save();

        // Create a recent log
        $recentLog = RegistrationAuditLog::create([
            'flow_id' => $flowId,
            'step' => 'recent_step',
            'action' => 'recent_action',
            'status' => 'success',
            'message' => 'Recent message',
        ]);

        $recentLogs = RegistrationAuditLog::recent(24)->get();

        expect($recentLogs)->toHaveCount(1);
        expect($recentLogs->first()->id)->toBe($recentLog->id);
        expect($recentLogs->first()->message)->toBe('Recent message');
    });

    it('handles null values gracefully', function () {
        $log = RegistrationAuditLog::create([
            'flow_id' => RegistrationAuditLog::generateFlowId(),
            'step' => 'test_nulls',
            'action' => 'test_null_handling',
            'status' => 'success',
            'message' => 'Testing null values',
            'request_data' => null,
            'response_data' => null,
            'metadata' => null,
            'user_id' => null,
            'email' => null,
            'selected_plan' => null,
            'error_message' => null,
            'stripe_session_id' => null,
        ]);

        expect($log->request_data)->toBeNull();
        expect($log->response_data)->toBeNull();
        expect($log->metadata)->toBeNull();
        expect($log->user_id)->toBeNull();
        expect($log->email)->toBeNull();
        expect($log->selected_plan)->toBeNull();
        expect($log->error_message)->toBeNull();
        expect($log->stripe_session_id)->toBeNull();
        expect($log->user)->toBeNull();
    });
});