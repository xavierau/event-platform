<?php

use App\Enums\CheckInMethod;
use App\Enums\CheckInStatus;
use App\Enums\RoleNameEnum;
use App\Models\Booking;
use App\Models\CheckInLog;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\Traits\CreatesTestData;

uses(RefreshDatabase::class, CreatesTestData::class);

beforeEach(function () {
    // Create roles
    Role::create(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::create(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);
});

describe('CheckInRecords Authorization', function () {
    test('platform admin can access check-in records page', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
    });

    test('organization admin can access check-in records page', function () {
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
    });

    test('regular user cannot access check-in records page', function () {
        $user = User::factory()->create();
        $user->assignRole(RoleNameEnum::USER);

        $response = $this->actingAs($user)->get(route('admin.check-in-records.index'));

        $response->assertStatus(403);
    });

    test('guest cannot access check-in records page', function () {
        $response = $this->get(route('admin.check-in-records.index'));

        $response->assertRedirect(route('login'));
    });
});

describe('CheckInRecords Data Filtering', function () {
    test('platform admin can see all check-in records', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        // Create complete events with proper relationships
        $event1 = $this->createCompleteEvent();
        $event2 = $this->createCompleteEvent();

        $occurrence1 = $event1->eventOccurrences->first();
        $occurrence2 = $event2->eventOccurrences->first();

        $ticketDef1 = $occurrence1->ticketDefinitions->first();
        $ticketDef2 = $occurrence2->ticketDefinitions->first();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $transaction1 = Transaction::factory()->create(['user_id' => $user1->id]);
        $transaction2 = Transaction::factory()->create(['user_id' => $user2->id]);

        $booking1 = Booking::factory()->create([
            'transaction_id' => $transaction1->id,
            'ticket_definition_id' => $ticketDef1->id,
            'event_id' => $event1->id,
        ]);
        $booking2 = Booking::factory()->create([
            'transaction_id' => $transaction2->id,
            'ticket_definition_id' => $ticketDef2->id,
            'event_id' => $event2->id,
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking1->id,
            'event_occurrence_id' => $occurrence1->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking2->id,
            'event_occurrence_id' => $occurrence2->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('records.data', 2)
        );
    });

    test('organization admin can only see their organization check-in records', function () {
        $orgAdmin = User::factory()->create();

        // Create two events with different organizers
        $event1 = $this->createCompleteEvent();
        $event2 = $this->createCompleteEvent();

        // Associate org admin with first event's organizer only
        OrganizerUser::create([
            'organizer_id' => $event1->organizer_id,
            'user_id' => $orgAdmin->id,
            'role_in_organizer' => 'admin',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $occurrence1 = $event1->eventOccurrences->first();
        $occurrence2 = $event2->eventOccurrences->first();

        $ticketDef1 = $occurrence1->ticketDefinitions->first();
        $ticketDef2 = $occurrence2->ticketDefinitions->first();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $transaction1 = Transaction::factory()->create(['user_id' => $user1->id]);
        $transaction2 = Transaction::factory()->create(['user_id' => $user2->id]);

        $booking1 = Booking::factory()->create([
            'transaction_id' => $transaction1->id,
            'ticket_definition_id' => $ticketDef1->id,
            'event_id' => $event1->id,
        ]);
        $booking2 = Booking::factory()->create([
            'transaction_id' => $transaction2->id,
            'ticket_definition_id' => $ticketDef2->id,
            'event_id' => $event2->id,
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking1->id,
            'event_occurrence_id' => $occurrence1->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking2->id,
            'event_occurrence_id' => $occurrence2->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($orgAdmin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('records.data', 1)
            ->where('records.data.0.event.organizer_id', $event1->organizer_id)
        );
    });
});

describe('CheckInRecords Search and Filtering', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN);

        $this->organizer = Organizer::factory()->create();
        $this->event = Event::factory()->create(['organizer_id' => $this->organizer->id]);
        $this->occurrence = EventOccurrence::factory()->create(['event_id' => $this->event->id]);

        $this->user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $this->transaction1 = Transaction::factory()->create(['user_id' => $this->user1->id]);
        $this->transaction2 = Transaction::factory()->create(['user_id' => $this->user2->id]);

        $this->ticketDef = TicketDefinition::factory()->create(['event_id' => $this->event->id]);

        $this->booking1 = Booking::factory()->create([
            'transaction_id' => $this->transaction1->id,
            'ticket_definition_id' => $this->ticketDef->id,
            'event_id' => $this->event->id,
            'booking_number' => 'BOOK001',
        ]);
        $this->booking2 = Booking::factory()->create([
            'transaction_id' => $this->transaction2->id,
            'ticket_definition_id' => $this->ticketDef->id,
            'event_id' => $this->event->id,
            'booking_number' => 'BOOK002',
        ]);
    });

    test('can search by user name', function () {
        CheckInLog::factory()->create([
            'booking_id' => $this->booking1->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $this->booking2->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['search' => 'John']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.booking.user.name', 'John Doe')
        );
    });

    test('can search by user email', function () {
        CheckInLog::factory()->create([
            'booking_id' => $this->booking1->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $this->booking2->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['search' => 'jane@example.com']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.booking.user.email', 'jane@example.com')
        );
    });

    test('can search by booking number', function () {
        CheckInLog::factory()->create([
            'booking_id' => $this->booking1->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $this->booking2->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['search' => 'BOOK001']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.booking.booking_number', 'BOOK001')
        );
    });

    test('can filter by status', function () {
        CheckInLog::factory()->create([
            'booking_id' => $this->booking1->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $this->booking2->id,
            'event_occurrence_id' => $this->occurrence->id,
            'status' => CheckInStatus::FAILED_INVALID_CODE,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', ['status' => CheckInStatus::SUCCESSFUL->value]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
            ->where('records.data.0.status', CheckInStatus::SUCCESSFUL->value)
        );
    });

    test('can filter by date range', function () {
        $checkIn1 = CheckInLog::factory()->create([
            'booking_id' => $this->booking1->id,
            'event_occurrence_id' => $this->occurrence->id,
            'check_in_timestamp' => Carbon::now()->subDays(5),
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        $checkIn2 = CheckInLog::factory()->create([
            'booking_id' => $this->booking2->id,
            'event_occurrence_id' => $this->occurrence->id,
            'check_in_timestamp' => Carbon::now()->subDays(1),
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.check-in-records.index', [
                'start_date' => Carbon::now()->subDays(3)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 1)
        );
    });
});

describe('CheckInRecords Pagination', function () {
    test('returns paginated results', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $organizer = Organizer::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $ticketDef = TicketDefinition::factory()->create(['event_id' => $event->id]);

        // Create 30 check-in records
        for ($i = 0; $i < 30; $i++) {
            $user = User::factory()->create();
            $transaction = Transaction::factory()->create(['user_id' => $user->id]);
            $booking = Booking::factory()->create([
                'transaction_id' => $transaction->id,
                'ticket_definition_id' => $ticketDef->id,
                'event_id' => $event->id,
            ]);
            CheckInLog::factory()->create([
                'booking_id' => $booking->id,
                'event_occurrence_id' => $occurrence->id,
                'status' => CheckInStatus::SUCCESSFUL,
            ]);
        }

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

        $organizer = Organizer::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $ticketDef = TicketDefinition::factory()->create(['event_id' => $event->id]);

        // Create 30 check-in records
        for ($i = 0; $i < 30; $i++) {
            $user = User::factory()->create();
            $transaction = Transaction::factory()->create(['user_id' => $user->id]);
            $booking = Booking::factory()->create([
                'transaction_id' => $transaction->id,
                'ticket_definition_id' => $ticketDef->id,
                'event_id' => $event->id,
            ]);
            CheckInLog::factory()->create([
                'booking_id' => $booking->id,
                'event_occurrence_id' => $occurrence->id,
                'status' => CheckInStatus::SUCCESSFUL,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index', ['page' => 2]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('records.data', 5)
            ->where('records.current_page', 2)
        );
    });
});

describe('CheckInRecords CSV Export', function () {
    test('platform admin can export CSV with all records', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $organizer = Organizer::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create(['name' => 'Test User']);
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);
        $ticketDef = TicketDefinition::factory()->create(['event_id' => $event->id]);
        $booking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'ticket_definition_id' => $ticketDef->id,
            'event_id' => $event->id,
            'booking_number' => 'BOOK001',
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
            'method' => CheckInMethod::QR_SCAN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        $content = $response->getContent();
        expect($content)->toContain('Check-in Time');
        expect($content)->toContain('Test User');
        expect($content)->toContain('BOOK001');
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

        $occurrence1 = EventOccurrence::factory()->create(['event_id' => $event1->id]);
        $occurrence2 = EventOccurrence::factory()->create(['event_id' => $event2->id]);

        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        $transaction1 = Transaction::factory()->create(['user_id' => $user1->id]);
        $transaction2 = Transaction::factory()->create(['user_id' => $user2->id]);

        $ticketDef1 = TicketDefinition::factory()->create();
        $ticketDef2 = TicketDefinition::factory()->create();

        // Attach ticket definitions to event occurrences
        $occurrence1->ticketDefinitions()->attach($ticketDef1->id);
        $occurrence2->ticketDefinitions()->attach($ticketDef2->id);

        $booking1 = Booking::factory()->create([
            'transaction_id' => $transaction1->id,
            'ticket_definition_id' => $ticketDef1->id,
            'event_id' => $event1->id,
        ]);
        $booking2 = Booking::factory()->create([
            'transaction_id' => $transaction2->id,
            'ticket_definition_id' => $ticketDef2->id,
            'event_id' => $event2->id,
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking1->id,
            'event_occurrence_id' => $occurrence1->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking2->id,
            'event_occurrence_id' => $occurrence2->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $response = $this->actingAs($orgAdmin)->get(route('admin.check-in-records.export'));

        $response->assertStatus(200);
        $content = $response->getContent();
        expect($content)->toContain('User One');
        expect($content)->not->toContain('User Two');
    });

    test('export respects search and filter parameters', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $organizer = Organizer::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $user1 = User::factory()->create(['name' => 'John Doe']);
        $user2 = User::factory()->create(['name' => 'Jane Smith']);

        $transaction1 = Transaction::factory()->create(['user_id' => $user1->id]);
        $transaction2 = Transaction::factory()->create(['user_id' => $user2->id]);

        $ticketDef = TicketDefinition::factory()->create(['event_id' => $event->id]);

        $booking1 = Booking::factory()->create([
            'transaction_id' => $transaction1->id,
            'ticket_definition_id' => $ticketDef->id,
            'event_id' => $event->id,
        ]);
        $booking2 = Booking::factory()->create([
            'transaction_id' => $transaction2->id,
            'ticket_definition_id' => $ticketDef->id,
            'event_id' => $event->id,
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking1->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking2->id,
            'event_occurrence_id' => $occurrence->id,
            'status' => CheckInStatus::FAILED_INVALID_CODE,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.check-in-records.export', [
                'search' => 'John',
                'status' => CheckInStatus::SUCCESSFUL->value,
            ]));

        $response->assertStatus(200);
        $content = $response->getContent();
        expect($content)->toContain('John Doe');
        expect($content)->not->toContain('Jane Smith');
    });
});

describe('CheckInRecords Data Structure', function () {
    test('returns proper data structure', function () {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNameEnum::ADMIN);

        $organizer = Organizer::factory()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $user = User::factory()->create();
        $operator = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);
        $ticketDef = TicketDefinition::factory()->create(['event_id' => $event->id]);
        $booking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'ticket_definition_id' => $ticketDef->id,
            'event_id' => $event->id,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $occurrence->id,
            'operator_user_id' => $operator->id,
            'status' => CheckInStatus::SUCCESSFUL,
            'method' => CheckInMethod::QR_SCAN,
            'location_description' => 'Main Entrance',
            'notes' => 'Test check-in',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.check-in-records.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/CheckInRecords/Index')
            ->has('records.data.0.id')
            ->has('records.data.0.check_in_timestamp')
            ->has('records.data.0.status')
            ->has('records.data.0.method')
            ->has('records.data.0.location_description')
            ->has('records.data.0.notes')
            ->has('records.data.0.booking.id')
            ->has('records.data.0.booking.booking_number')
            ->has('records.data.0.booking.user.id')
            ->has('records.data.0.booking.user.name')
            ->has('records.data.0.booking.user.email')
            ->has('records.data.0.event.id')
            ->has('records.data.0.event.name')
            ->has('records.data.0.event_occurrence.id')
            ->has('records.data.0.event_occurrence.name')
            ->has('records.data.0.operator.id')
            ->has('records.data.0.operator.name')
        );
    });
});
