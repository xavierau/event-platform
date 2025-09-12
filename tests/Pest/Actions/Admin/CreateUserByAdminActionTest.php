<?php

use App\Actions\Admin\CreateUserByAdminAction;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Modules\Membership\Actions\AssignMembershipLevelAction;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->adminUser = User::factory()->create();
    $this->actingAs($this->adminUser);
    
    $this->action = new CreateUserByAdminAction(
        new AssignMembershipLevelAction()
    );
});

describe('CreateUserByAdminAction', function () {
    
    test('can create a user with valid data', function () {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile_number' => '+1234567890',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->name)->toBe('John Doe')
            ->and($user->email)->toBe('john@example.com')
            ->and($user->mobile_number)->toBe('+1234567890')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->is_commenting_blocked)->toBeFalse()
            ->and(Hash::check('password123', $user->password))->toBeTrue();

        // Verify it was persisted to database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    });

    test('creates audit log entry when creating user', function () {
        $userData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData, null, null, 'Test user creation');

        // Check audit log was created
        $auditLog = AdminAuditLog::where('target_user_id', $user->id)->first();
        
        expect($auditLog)->not->toBeNull()
            ->and($auditLog->admin_user_id)->toBe($this->adminUser->id)
            ->and($auditLog->action_type)->toBe('create_user')
            ->and($auditLog->reason)->toBe('Test user creation')
            ->and($auditLog->action_details['user_email'])->toBe('jane@example.com')
            ->and($auditLog->action_details['user_name'])->toBe('Jane Doe')
            ->and($auditLog->action_details['membership_assigned'])->toBeFalse();
    });

    test('can create user with membership level', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium'],
            'duration_months' => 12,
        ]);
        
        $userData = [
            'name' => 'Premium User',
            'email' => 'premium@example.com',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData, $membershipLevel->id);

        expect($user)->toBeInstanceOf(User::class);
        
        // Check user has membership
        $membership = UserMembership::where('user_id', $user->id)->first();
        expect($membership)->not->toBeNull()
            ->and($membership->membership_level_id)->toBe($membershipLevel->id)
            ->and($membership->started_at)->not->toBeNull()
            ->and($membership->expires_at)->not->toBeNull();

        // Check audit log includes membership details
        $auditLog = AdminAuditLog::where('target_user_id', $user->id)->first();
        expect($auditLog->action_details['membership_assigned'])->toBeTrue()
            ->and($auditLog->action_details['membership_level']['id'])->toBe($membershipLevel->id)
            ->and($auditLog->action_details['membership_level']['name'])->toEqual($membershipLevel->name);
    });

    test('can create user with custom membership duration', function () {
        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Custom'],
            'duration_months' => 12,
        ]);
        
        $userData = [
            'name' => 'Custom User',
            'email' => 'custom@example.com',
            'password' => 'password123',
        ];

        $customDuration = 6; // 6 months instead of default 12
        $user = $this->action->execute($userData, $membershipLevel->id, $customDuration);

        // Check membership has custom duration
        $membership = UserMembership::where('user_id', $user->id)->first();
        expect($membership)->not->toBeNull();
        
        $expectedExpiry = $membership->started_at->addMonths($customDuration);
        expect($membership->expires_at->format('Y-m-d'))->toBe($expectedExpiry->format('Y-m-d'));

        // Check audit log includes custom duration
        $auditLog = AdminAuditLog::where('target_user_id', $user->id)->first();
        expect($auditLog->action_details['membership_level']['custom_duration_months'])->toBe(6);
    });

    test('creates user without membership when no level specified', function () {
        $userData = [
            'name' => 'Basic User',
            'email' => 'basic@example.com',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData);

        // Check no membership was created
        $membership = UserMembership::where('user_id', $user->id)->first();
        expect($membership)->toBeNull();

        // Check audit log reflects no membership
        $auditLog = AdminAuditLog::where('target_user_id', $user->id)->first();
        expect($auditLog->action_details['membership_assigned'])->toBeFalse();
    });

    test('creates user with mobile number', function () {
        $userData = [
            'name' => 'Mobile User',
            'email' => 'mobile@example.com',
            'mobile_number' => '+1987654321',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData);

        expect($user->mobile_number)->toBe('+1987654321');

        $auditLog = AdminAuditLog::where('target_user_id', $user->id)->first();
        expect($auditLog->action_details['mobile_number'])->toBe('+1987654321');
    });

    test('creates user without mobile number', function () {
        $userData = [
            'name' => 'No Mobile User',
            'email' => 'nomobile@example.com',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData);

        expect($user->mobile_number)->toBeNull();

        $auditLog = AdminAuditLog::where('target_user_id', $user->id)->first();
        expect($auditLog->action_details['mobile_number'])->toBeNull();
    });

    test('auto-verifies email for admin-created users', function () {
        $userData = [
            'name' => 'Verified User',
            'email' => 'verified@example.com',
            'password' => 'password123',
        ];

        $user = $this->action->execute($userData);

        expect($user->email_verified_at)->not->toBeNull();
    });

    test('throws exception when membership level not found', function () {
        $userData = [
            'name' => 'Error User',
            'email' => 'error@example.com',
            'password' => 'password123',
        ];

        expect(fn() => $this->action->execute($userData, 99999))
            ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    test('transaction rolls back on failure', function () {
        $userData = [
            'name' => 'Transaction User',
            'email' => 'transaction@example.com',
            'password' => 'password123',
        ];

        // Mock the AssignMembershipLevelAction to throw an exception
        $mockAction = Mockery::mock(AssignMembershipLevelAction::class);
        $mockAction->shouldReceive('execute')->andThrow(new Exception('Membership assignment failed'));
        
        $action = new CreateUserByAdminAction($mockAction);

        $membershipLevel = MembershipLevel::factory()->create();

        expect(fn() => $action->execute($userData, $membershipLevel->id))
            ->toThrow(Exception::class);

        // Verify user was not created (transaction rolled back)
        $this->assertDatabaseMissing('users', [
            'email' => 'transaction@example.com',
        ]);

        // Verify no audit log was created
        expect(AdminAuditLog::where('admin_user_id', $this->adminUser->id)->count())->toBe(0);
    });
});