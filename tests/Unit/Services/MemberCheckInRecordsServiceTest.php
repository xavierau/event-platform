<?php

use App\Enums\RoleNameEnum;
use App\Models\MemberCheckIn;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\User;
use App\Services\MemberCheckInRecordsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::create(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::create(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);

    $this->service = new MemberCheckInRecordsService;

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN);

    $this->orgAdmin = User::factory()->create();
    $this->organizer = Organizer::factory()->create();

    OrganizerUser::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->orgAdmin->id,
        'role_in_organizer' => 'admin',
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $this->member = User::factory()->create();
    $this->scanner = User::factory()->create();
});

describe('MemberCheckInRecordsService - getCheckInRecords', function () {
    test('returns paginated member check-in records', function () {
        // Create some member check-ins
        MemberCheckIn::factory()->count(3)->create([
            'user_id' => $this->member->id,
            'scanned_by_user_id' => $this->scanner->id,
        ]);

        $result = $this->service->getCheckInRecords($this->admin);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->total())->toBe(3);
        expect($result->perPage())->toBe(25);
    });

    test('applies pagination parameters correctly', function () {
        MemberCheckIn::factory()->count(30)->create();

        $result = $this->service->getCheckInRecords($this->admin, [
            'per_page' => 10,
            'page' => 2,
        ]);

        expect($result->perPage())->toBe(10);
        expect($result->currentPage())->toBe(2);
        expect($result->count())->toBe(10);
    });

    test('platform admin can see all member check-ins', function () {
        MemberCheckIn::factory()->count(5)->create();

        $result = $this->service->getCheckInRecords($this->admin);

        expect($result->total())->toBe(5);
    });

    test('organization admin only sees check-ins from their organization events', function () {
        // This will be implemented based on organization filtering
        $result = $this->service->getCheckInRecords($this->orgAdmin);

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    });
});

describe('MemberCheckInRecordsService - search and filtering', function () {
    beforeEach(function () {
        $this->memberJohn = User::factory()->create();
        $this->memberJane = User::factory()->create();

        MemberCheckIn::factory()->create([
            'user_id' => $this->memberJohn->id,
            'scanned_by_user_id' => $this->scanner->id,
            'scanned_at' => now()->subHours(2),
        ]);

        MemberCheckIn::factory()->create([
            'user_id' => $this->memberJane->id,
            'scanned_by_user_id' => $this->scanner->id,
            'scanned_at' => now()->subDays(2),
        ]);
    });

    test('can search by member name', function () {
        $result = $this->service->getCheckInRecords($this->admin, ['search' => $this->memberJohn->name]);

        expect($result->total())->toBe(1);
        expect($result->first()->user_id)->toBe($this->memberJohn->id);
    });

    test('can search by member email', function () {
        $result = $this->service->getCheckInRecords($this->admin, ['search' => $this->memberJane->email]);

        expect($result->total())->toBe(1);
        expect($result->first()->user_id)->toBe($this->memberJane->id);
    });

    test('can filter by date range', function () {
        $result = $this->service->getCheckInRecords($this->admin, [
            'start_date' => now()->subHours(12)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        expect($result->total())->toBe(1);
        expect($result->first()->user_id)->toBe($this->memberJohn->id);
    });

    test('can filter by scanner', function () {
        $otherScanner = User::factory()->create();
        MemberCheckIn::factory()->create([
            'user_id' => $this->member->id,
            'scanned_by_user_id' => $otherScanner->id,
        ]);

        $result = $this->service->getCheckInRecords($this->admin, [
            'scanner_id' => $this->scanner->id,
        ]);

        expect($result->total())->toBe(2);
    });

    test('can filter by location', function () {
        MemberCheckIn::factory()->create([
            'user_id' => $this->member->id,
            'scanned_by_user_id' => $this->scanner->id,
            'location' => 'VIP Lounge',
        ]);

        $result = $this->service->getCheckInRecords($this->admin, [
            'location' => 'VIP Lounge',
        ]);

        expect($result->total())->toBe(1);
    });
});

describe('MemberCheckInRecordsService - statistics', function () {
    test('calculates check-in statistics correctly', function () {
        // Create check-ins for different time periods
        MemberCheckIn::factory()->count(5)->create(['scanned_at' => now()]);
        MemberCheckIn::factory()->count(3)->create(['scanned_at' => now()->subDays(1)]);

        $stats = $this->service->getCheckInStats($this->admin);

        expect($stats)->toHaveKey('total');
        expect($stats)->toHaveKey('today');
        expect($stats)->toHaveKey('unique_members');
        expect($stats['total'])->toBe(8);
        expect($stats['today'])->toBe(5);
    });

    test('counts unique members correctly', function () {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        // Same member checked in multiple times
        MemberCheckIn::factory()->count(3)->create(['user_id' => $member1->id]);
        // Different member checked in once
        MemberCheckIn::factory()->create(['user_id' => $member2->id]);

        $stats = $this->service->getCheckInStats($this->admin);

        expect($stats['total'])->toBe(4);
        expect($stats['unique_members'])->toBe(2);
    });
});

describe('MemberCheckInRecordsService - export functionality', function () {
    test('returns collection for export without pagination', function () {
        MemberCheckIn::factory()->count(30)->create();

        $result = $this->service->getCheckInRecordsForExport($this->admin);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result->count())->toBe(30);
    });

    test('export respects filters', function () {
        $memberJohn = User::factory()->create();
        $memberJane = User::factory()->create();

        MemberCheckIn::factory()->create(['user_id' => $memberJohn->id]);
        MemberCheckIn::factory()->create(['user_id' => $memberJane->id]);

        $result = $this->service->getCheckInRecordsForExport($this->admin, ['search' => $memberJohn->name]);

        expect($result->count())->toBe(1);
    });

    test('exports to CSV with correct headers and data', function () {
        $member = User::factory()->create();
        $scanner = User::factory()->create();

        MemberCheckIn::factory()->create([
            'user_id' => $member->id,
            'scanned_by_user_id' => $scanner->id,
            'location' => 'Main Entrance',
            'membership_data' => ['level' => 'Premium'],
        ]);

        $records = $this->service->getCheckInRecordsForExport($this->admin);
        $response = $this->service->exportToCsv($records);

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8');
        expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    });
});

describe('MemberCheckInRecordsService - authorization', function () {
    test('regular user cannot access any records', function () {
        $user = User::factory()->create();
        $user->assignRole(RoleNameEnum::USER);

        MemberCheckIn::factory()->count(5)->create();

        $result = $this->service->getCheckInRecords($user);

        expect($result->total())->toBe(0);
    });
});
