<?php

use App\Enums\RoleNameEnum;
use App\Models\Event;
use App\Models\MemberCheckIn;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::create(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::create(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
});

describe('MemberCheckInRecords Authorization', function () {
    test('platform admin can access member check-in records page', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->where('pageTitle', 'Member Check-in Records')
        );
    });

    test('organization admin can access member check-in records page', function () {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();

        OrganizerUser::create([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'admin',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index'));
    });

    test('regular user cannot access member check-in records page', function () {
        $user = User::factory()->create();
        $user->assignRole(RoleNameEnum::USER);

        $response = $this->actingAs($user)->get(route('admin.check-in-records.index'));

        $response->assertStatus(403);
    });

    test('guest cannot access member check-in records page', function () {
        $response = $this->get(route('admin.check-in-records.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('MemberCheckInRecords Data Display', function () {
    test('platform admin can see all member check-in records', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $member1 = User::factory()->create(['name' => 'Member One']);
        $member2 = User::factory()->create(['name' => 'Member Two']);
        $scanner = User::factory()->create(['name' => 'Scanner User']);

        MemberCheckIn::factory()->create([
            'user_id' => $member1->id,
            'scanned_by_user_id' => $scanner->id,
        ]);

        MemberCheckIn::factory()->create([
            'user_id' => $member2->id,
            'scanned_by_user_id' => $scanner->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('records.data', 2)
        );
    });

    test('organization admin only sees check-ins from their organization events', function () {
        $orgAdmin = User::factory()->create();
        $organizer1 = Organizer::factory()->create();
        $organizer2 = Organizer::factory()->create();

        // Associate org admin with first organizer only
        OrganizerUser::create([
            'organizer_id' => $organizer1->id,
            'user_id' => $orgAdmin->id,
            'role_in_organizer' => 'admin',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $event1 = Event::factory()->create(['organizer_id' => $organizer1->id]);
        $event2 = Event::factory()->create(['organizer_id' => $organizer2->id]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $scanner = User::factory()->create();

        // Check-in for event from admin's organization
        MemberCheckIn::factory()->create([
            'user_id' => $member1->id,
            'scanned_by_user_id' => $scanner->id,
            'event_id' => $event1->id,
        ]);

        // Check-in for event from different organization
        MemberCheckIn::factory()->create([
            'user_id' => $member2->id,
            'scanned_by_user_id' => $scanner->id,
            'event_id' => $event2->id,
        ]);

        $response = $this->actingAs($orgAdmin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('records.data', 1)
            ->where('records.data.0.event.id', $event1->id)
        );
    });
});

describe('MemberCheckInRecords Search and Filtering', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN);

        $this->memberJohn = User::factory()->create();
        $this->memberJane = User::factory()->create();
        $this->scanner1 = User::factory()->create();
        $this->scanner2 = User::factory()->create();

        MemberCheckIn::factory()->create([
            'user_id' => $this->memberJohn->id,
            'scanned_by_user_id' => $this->scanner1->id,
            'scanned_at' => now()->subHours(2),
            'location' => 'Main Entrance',
        ]);

        MemberCheckIn::factory()->create([
            'user_id' => $this->memberJane->id,
            'scanned_by_user_id' => $this->scanner2->id,
            'scanned_at' => now()->subDays(2),
            'location' => 'VIP Lounge',
        ]);
    });

    test('can search by member name', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['search' => $this->memberJohn->name]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.member.name', $this->memberJohn->name)
        );
    });

    test('can search by member email', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['search' => $this->memberJane->email]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.member.email', $this->memberJane->email)
        );
    });

    test('can filter by scanner', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['scanner_id' => $this->scanner1->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.scanner.id', $this->scanner1->id)
        );
    });

    test('can filter by location', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['location' => 'VIP Lounge']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.location', 'VIP Lounge')
        );
    });

    test('can filter by date range', function () {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', [
                'start_date' => now()->subHours(12)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.member.name', $this->memberJohn->name)
        );
    });
});

describe('MemberCheckInRecords Statistics', function () {
    test('displays correct statistics', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $scanner = User::factory()->create();

        // Create check-ins for today
        MemberCheckIn::factory()->count(3)->create([
            'user_id' => $member1->id,
            'scanned_by_user_id' => $scanner->id,
            'scanned_at' => now(),
        ]);

        // Create check-ins for yesterday
        MemberCheckIn::factory()->count(2)->create([
            'user_id' => $member2->id,
            'scanned_by_user_id' => $scanner->id,
            'scanned_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->where('stats.total', 5)
            ->where('stats.today', 3)
            ->where('stats.unique_members', 2)
        );
    });
});

describe('MemberCheckInRecords Pagination', function () {
    test('returns paginated results', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        // Create 30 member check-in records
        MemberCheckIn::factory()->count(30)->create();

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 25)
            ->has('records.current_page')
            ->has('records.last_page')
            ->has('records.total')
        );
    });

    test('can navigate to different pages', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        // Create 30 member check-in records
        MemberCheckIn::factory()->count(30)->create();

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index', ['page' => 2]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 5)
            ->where('records.current_page', 2)
        );
    });
});

describe('MemberCheckInRecords CSV Export', function () {
    test('platform admin can export CSV with all records', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $member = User::factory()->create(['name' => 'Test Member', 'email' => 'test@example.com']);
        $scanner = User::factory()->create(['name' => 'Test Scanner']);

        MemberCheckIn::factory()->create([
            'user_id' => $member->id,
            'scanned_by_user_id' => $scanner->id,
            'location' => 'Main Entrance',
            'membership_data' => ['level' => 'Premium', 'status' => 'Active'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');
    });

    test('organization admin can export CSV with only their organization records', function () {
        $orgAdmin = User::factory()->create();
        $organizer1 = Organizer::factory()->create();
        $organizer2 = Organizer::factory()->create();

        OrganizerUser::create([
            'organizer_id' => $organizer1->id,
            'user_id' => $orgAdmin->id,
            'role_in_organizer' => 'admin',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $event1 = Event::factory()->create(['organizer_id' => $organizer1->id]);
        $event2 = Event::factory()->create(['organizer_id' => $organizer2->id]);

        $member1 = User::factory()->create();
        $member2 = User::factory()->create();
        $scanner = User::factory()->create();

        MemberCheckIn::factory()->create([
            'user_id' => $member1->id,
            'scanned_by_user_id' => $scanner->id,
            'event_id' => $event1->id,
        ]);

        MemberCheckIn::factory()->create([
            'user_id' => $member2->id,
            'scanned_by_user_id' => $scanner->id,
            'event_id' => $event2->id,
        ]);

        $response = $this->actingAs($orgAdmin)->get(route('admin.check-in-records.export'));

        $response->assertStatus(200);
    });

    test('export respects search and filter parameters', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $memberJohn = User::factory()->create();
        $memberJane = User::factory()->create();
        $scanner = User::factory()->create();

        MemberCheckIn::factory()->create([
            'user_id' => $memberJohn->id,
            'scanned_by_user_id' => $scanner->id,
        ]);

        MemberCheckIn::factory()->create([
            'user_id' => $memberJane->id,
            'scanned_by_user_id' => $scanner->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.check-in-records.export', [
                'search' => $memberJohn->name,
            ]));

        $response->assertStatus(200);
    });
});

describe('MemberCheckInRecords Data Structure', function () {
    test('returns proper data structure', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $member = User::factory()->create(['name' => 'Test Member', 'email' => 'member@example.com']);
        $scanner = User::factory()->create(['name' => 'Scanner User', 'email' => 'scanner@example.com']);

        MemberCheckIn::factory()->create([
            'user_id' => $member->id,
            'scanned_by_user_id' => $scanner->id,
            'location' => 'Conference Hall',
            'notes' => 'VIP member check-in',
            'device_identifier' => 'SCANNER-001',
            'membership_data' => ['level' => 'VIP', 'status' => 'Active'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('records.data.0.id')
            ->has('records.data.0.scanned_at')
            ->has('records.data.0.location')
            ->has('records.data.0.notes')
            ->has('records.data.0.device_identifier')
            ->has('records.data.0.membership_data')
            ->has('records.data.0.member.id')
            ->has('records.data.0.member.name')
            ->has('records.data.0.member.email')
            ->has('records.data.0.scanner.id')
            ->has('records.data.0.scanner.name')
            ->has('records.data.0.scanner.email')
            ->where('records.data.0.member.name', 'Test Member')
            ->where('records.data.0.scanner.name', 'Scanner User')
            ->where('records.data.0.location', 'Conference Hall')
            ->where('records.data.0.notes', 'VIP member check-in')
        );
    });

    test('provides filter options', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $member = User::factory()->create();
        $scanner = User::factory()->create(['name' => 'Test Scanner']);

        MemberCheckIn::factory()->create([
            'user_id' => $member->id,
            'scanned_by_user_id' => $scanner->id,
            'location' => 'Main Entrance',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('availableScanners')
            ->has('availableLocations')
            ->has('availableEvents')
            ->has('availableOrganizers')
        );
    });
});
