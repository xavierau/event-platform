<?php

use App\Enums\RoleNameEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('MemberScanner Integration Test', function () {
    beforeEach(function () {
        // Disable all middleware for cleaner testing
        $this->withoutMiddleware();
        // Disable Telescope to avoid table issues in tests
        config(['telescope.enabled' => false]);
        
        // Create roles if they don't exist
        Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value]);
        Role::firstOrCreate(['name' => RoleNameEnum::USER->value]);
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(RoleNameEnum::ADMIN->value);
        
        $this->memberUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        $this->validQrCode = json_encode([
            'userId' => $this->memberUser->id,
            'userName' => $this->memberUser->name,
            'email' => $this->memberUser->email,
            'membershipLevel' => 'Premium',
            'membershipStatus' => 'Active',
            'timestamp' => now()->toISOString(),
        ]);
    });

    it('can validate a valid member QR code', function () {
        $this->actingAs($this->adminUser);
        
        $response = $this->postJson(route('admin.member-scanner.validate'), [
            'qr_code' => $this->validQrCode,
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'member' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    });

    it('can process a member check-in', function () {
        $this->actingAs($this->adminUser);
        
        $checkInData = [
            'qr_code' => $this->validQrCode,
            'location' => 'Main Entrance',
            'notes' => 'Regular check-in',
        ];
        
        $response = $this->postJson(route('admin.member-scanner.check-in'), $checkInData);
        
        // Debug the response if it's not 204
        if ($response->status() !== 204) {
            dump('Response Status: ' . $response->status());
            dump('Response Content: ' . $response->content());
        }
        
        $response->assertStatus(204);
        
        // Verify check-in was recorded in database
        $this->assertDatabaseHas('member_check_ins', [
            'user_id' => $this->memberUser->id,
            'scanned_by_user_id' => $this->adminUser->id,
            'location' => 'Main Entrance',
        ]);
    });

    it('can retrieve member check-in history', function () {
        $this->actingAs($this->adminUser);
        
        // First create a check-in
        \App\Models\MemberCheckIn::factory()->create([
            'user_id' => $this->memberUser->id,
            'scanned_by_user_id' => $this->adminUser->id,
        ]);
        
        $response = $this->getJson(route('admin.member-scanner.history', $this->memberUser->id));
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'history' => [
                    '*' => [
                        'id',
                        'scanned_at',
                        'location',
                    ]
                ],
            ]);
    });
});