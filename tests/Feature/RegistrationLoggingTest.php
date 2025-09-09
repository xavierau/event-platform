<?php

use App\Models\FrontendLog;
use App\Models\RegistrationAuditLog;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create membership levels for testing
    MembershipLevel::create([
        'name' => ['en' => 'Free'],
        'slug' => 'free',
        'description' => ['en' => 'Free tier'],
        'price' => 0,
        'stripe_product_id' => 'prod_free',
        'stripe_price_id' => 'price_free',
        'benefits' => ['basic_access'],
        'duration_months' => null,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    MembershipLevel::create([
        'name' => ['en' => 'Premium'],
        'slug' => 'premium',
        'description' => ['en' => 'Premium tier'],
        'price' => 2900,
        'stripe_product_id' => 'prod_premium',
        'stripe_price_id' => 'price_premium_monthly',
        'benefits' => ['premium_access'],
        'duration_months' => 12,
        'is_active' => true,
        'sort_order' => 2,
    ]);
});

describe('Registration Audit Logging', function () {
    it('logs registration page visit', function () {
        $response = $this->get(route('register.subscription.create'));

        $response->assertOk();

        // Check that audit log entry was created for page visit
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'registration_page_visit',
            'action' => 'page_loaded',
            'status' => 'success',
        ]);

        $auditLog = RegistrationAuditLog::where('step', 'registration_page_visit')->first();
        expect($auditLog->flow_id)->not->toBeNull();
        expect($auditLog->message)->toBe('User visited registration page with pricing plans');
        expect($auditLog->metadata)->toHaveKey('user_agent');
        expect($auditLog->metadata)->toHaveKey('ip_address');
    });

    it('logs membership levels loading', function () {
        $response = $this->get(route('register.subscription.create'));

        $response->assertOk();

        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'membership_levels_loading',
            'action' => 'data_loaded',
            'status' => 'success',
        ]);

        $auditLog = RegistrationAuditLog::where('step', 'membership_levels_loading')->first();
        expect($auditLog->response_data)->toHaveKey('membership_levels_count');
        expect($auditLog->response_data['membership_levels_count'])->toBe(2);
        expect($auditLog->response_data)->toHaveKey('available_plans');
    });

    it('logs form submission with successful registration', function () {
        $response = $this->post(route('register.subscription.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'mobile_number' => '+1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'selected_price_id' => 'price_free',
        ]);

        $response->assertRedirect(route('register.subscription.success'));

        // Check form submission log
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'form_submission',
            'action' => 'form_submitted',
            'status' => 'success',
            'email' => 'test@example.com',
            'selected_plan' => 'price_free',
        ]);

        // Check validation success log
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'form_validation',
            'action' => 'validation_passed',
            'status' => 'success',
            'email' => 'test@example.com',
        ]);

        // Check registration completion log
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'registration_processing',
            'action' => 'registration_completed',
            'status' => 'success',
            'email' => 'test@example.com',
        ]);

        // Check free plan completion log
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'free_plan_completion',
            'action' => 'free_plan_registered',
            'status' => 'success',
            'email' => 'test@example.com',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $auditLogs = RegistrationAuditLog::where('email', 'test@example.com')->get();
        
        // All logs should have the same flow_id and user_id
        expect($auditLogs->pluck('flow_id')->unique())->toHaveCount(1);
        expect($auditLogs->where('user_id', '!=', null)->pluck('user_id')->unique())->toContain($user->id);
    });

    it('logs validation failures', function () {
        $response = $this->post(route('register.subscription.store'), [
            'name' => '',
            'email' => 'invalid-email',
            'mobile_number' => '',
            'password' => '123',
            'password_confirmation' => '456',
            'selected_price_id' => 'price_nonexistent',
        ]);

        $response->assertSessionHasErrors();

        // Check form submission log (still successful submission attempt)
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'form_submission',
            'action' => 'form_submitted',
            'status' => 'success',
        ]);

        // Check validation failure log
        $this->assertDatabaseHas('registration_audit_logs', [
            'step' => 'form_validation',
            'action' => 'validation_failed',
            'status' => 'failed',
        ]);

        $validationLog = RegistrationAuditLog::where('action', 'validation_failed')->first();
        expect($validationLog->error_message)->toContain('Validation errors:');
        expect($validationLog->response_data)->toHaveKey('validation_errors');
    });

    it('logs user creation process in service layer', function () {
        // Mock the action to simulate user creation
        Log::spy();

        $response = $this->post(route('register.subscription.store'), [
            'name' => 'Service User',
            'email' => 'service@example.com',
            'mobile_number' => '+1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'selected_price_id' => 'price_free',
        ]);

        $response->assertRedirect();

        // Verify registration service logs were created
        Log::shouldHaveReceived('channel')
            ->with('registration')
            ->atLeast()
            ->once();
    });
});

describe('Frontend Logging API', function () {
    it('accepts and stores frontend logs', function () {
        $logs = [
            [
                'level' => 'info',
                'component' => 'Registration',
                'message' => 'User selected membership plan',
                'data' => ['plan_id' => 1, 'plan_slug' => 'premium'],
                'timestamp' => now()->toISOString(),
                'url' => 'http://localhost/register',
                'userAgent' => 'Mozilla/5.0 Test Browser',
            ],
            [
                'level' => 'error',
                'component' => 'Registration',
                'message' => 'Form validation errors occurred',
                'data' => ['errors' => ['email' => 'Email is required']],
                'timestamp' => now()->toISOString(),
                'url' => 'http://localhost/register',
                'userAgent' => 'Mozilla/5.0 Test Browser',
            ],
        ];

        $response = $this->postJson('/api/frontend-logs', ['logs' => $logs]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        // Check that frontend logs were stored in database
        $this->assertDatabaseHas('frontend_logs', [
            'level' => 'info',
            'component' => 'Registration',
            'message' => 'User selected membership plan',
            'url' => 'http://localhost/register',
        ]);

        $this->assertDatabaseHas('frontend_logs', [
            'level' => 'error',
            'component' => 'Registration',
            'message' => 'Form validation errors occurred',
        ]);

        $frontendLog = FrontendLog::where('level', 'info')->first();
        expect($frontendLog->data)->toHaveKey('plan_id');
        expect($frontendLog->data['plan_slug'])->toBe('premium');
    });

    it('validates frontend log payload', function () {
        $invalidLogs = [
            [
                'level' => 'invalid_level',
                'component' => '',
                'message' => '',
                'timestamp' => 'invalid_timestamp',
                'url' => '',
                'userAgent' => '',
            ],
        ];

        $response = $this->postJson('/api/frontend-logs', ['logs' => $invalidLogs]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'logs.0.level',
                'logs.0.component',
                'logs.0.message',
                'logs.0.url',
                'logs.0.userAgent',
            ]);
    });

    it('associates logs with authenticated user', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $logs = [
            [
                'level' => 'info',
                'component' => 'Registration',
                'message' => 'Authenticated user action',
                'timestamp' => now()->toISOString(),
                'url' => 'http://localhost/register',
                'userAgent' => 'Mozilla/5.0 Test Browser',
            ],
        ];

        $response = $this->postJson('/api/frontend-logs', ['logs' => $logs]);

        $response->assertOk();

        $this->assertDatabaseHas('frontend_logs', [
            'user_id' => $user->id,
            'message' => 'Authenticated user action',
        ]);
    });

    it('handles too many logs gracefully', function () {
        $logs = [];
        for ($i = 0; $i < 101; $i++) {
            $logs[] = [
                'level' => 'info',
                'component' => 'Registration',
                'message' => "Log message {$i}",
                'timestamp' => now()->toISOString(),
                'url' => 'http://localhost/register',
                'userAgent' => 'Mozilla/5.0 Test Browser',
            ];
        }

        $response = $this->postJson('/api/frontend-logs', ['logs' => $logs]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logs']);
    });
});

describe('Registration Success and Error Flows', function () {
    it('logs success page visit', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Set up a flow_id in session
        session(['registration_flow_id' => 'test-flow-123']);

        $response = $this->get(route('register.subscription.success', ['session_id' => 'sess_test123']));

        // Should log success page visit
        $this->assertDatabaseHas('registration_audit_logs', [
            'flow_id' => 'test-flow-123',
            'step' => 'success_page_visit',
            'action' => 'success_page_loaded',
            'status' => 'success',
            'user_id' => $user->id,
        ]);
    });

    it('logs registration cancellation', function () {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        session(['registration_flow_id' => 'test-flow-cancel']);

        $response = $this->get(route('register.subscription.cancel'));

        $response->assertOk();

        // Should log cancellation
        $this->assertDatabaseHas('registration_audit_logs', [
            'flow_id' => 'test-flow-cancel',
            'step' => 'registration_cancelled',
            'action' => 'cancellation_page_visited',
            'status' => 'cancelled',
        ]);

        // Should log user cleanup since user was unverified
        $this->assertDatabaseHas('registration_audit_logs', [
            'flow_id' => 'test-flow-cancel',
            'step' => 'user_cleanup',
            'action' => 'unverified_user_deleted',
            'status' => 'success',
        ]);

        // User should be deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

    it('logs pending payment status', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        session(['registration_flow_id' => 'test-flow-pending']);

        $response = $this->get(route('register.subscription.pending'));

        $response->assertOk();

        $this->assertDatabaseHas('registration_audit_logs', [
            'flow_id' => 'test-flow-pending',
            'step' => 'registration_pending',
            'action' => 'pending_page_visited',
            'status' => 'pending',
            'user_id' => $user->id,
        ]);
    });
});

describe('Registration Audit Log Model', function () {
    it('generates unique flow ids', function () {
        $flowId1 = RegistrationAuditLog::generateFlowId();
        $flowId2 = RegistrationAuditLog::generateFlowId();

        expect($flowId1)->not->toBe($flowId2);
        expect($flowId1)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('sanitizes sensitive data', function () {
        $sensitiveData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'credit_card' => '4242424242424242',
            'api_key' => 'secret_key_123',
        ];

        $auditLog = RegistrationAuditLog::logStep([
            'flow_id' => RegistrationAuditLog::generateFlowId(),
            'step' => 'test',
            'action' => 'test_sanitization',
            'status' => 'success',
            'message' => 'Testing data sanitization',
            'request_data' => $sensitiveData,
        ]);

        expect($auditLog->request_data['name'])->toBe('Test User');
        expect($auditLog->request_data['email'])->toBe('test@example.com');
        expect($auditLog->request_data['password'])->toBe('[REDACTED]');
        expect($auditLog->request_data['password_confirmation'])->toBe('[REDACTED]');
        expect($auditLog->request_data['credit_card'])->toBe('[REDACTED]');
        expect($auditLog->request_data['api_key'])->toBe('[REDACTED]');
    });

    it('provides scopes for querying logs', function () {
        $flowId = RegistrationAuditLog::generateFlowId();

        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'form_submission',
            'action' => 'form_submitted',
            'status' => 'success',
            'message' => 'Test message',
            'email' => 'scope-test@example.com',
        ]);

        RegistrationAuditLog::logStep([
            'flow_id' => $flowId,
            'step' => 'form_validation',
            'action' => 'validation_failed',
            'status' => 'failed',
            'message' => 'Test message',
            'email' => 'scope-test@example.com',
        ]);

        // Test scopes
        expect(RegistrationAuditLog::byFlow($flowId)->count())->toBe(2);
        expect(RegistrationAuditLog::byStep('form_submission')->count())->toBe(1);
        expect(RegistrationAuditLog::failed()->count())->toBe(1);
        expect(RegistrationAuditLog::successful()->count())->toBe(1);
        expect(RegistrationAuditLog::byEmail('scope-test@example.com')->count())->toBe(2);
    });
});