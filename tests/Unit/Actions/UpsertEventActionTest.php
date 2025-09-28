<?php

use App\Actions\Event\UpsertEventAction;
use App\DataTransferObjects\EventData;
use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->action = new UpsertEventAction;

    // Create test user and authenticate
    $this->user = User::factory()->create();
    Auth::login($this->user);

    // Create required related models
    $this->organizer = Organizer::factory()->create();
    $this->category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $this->membershipLevel1 = MembershipLevel::factory()->create(['name' => ['en' => 'Bronze']]);
    $this->membershipLevel2 = MembershipLevel::factory()->create(['name' => ['en' => 'Gold']]);
});

describe('UpsertEventAction - Create Event', function () {
    it('creates event with action_type and visible_to_membership_levels', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Test Event', 'zh-TW' => '測試活動'],
            'short_summary' => ['en' => 'Test Summary', 'zh-TW' => '測試摘要'],
            'description' => ['en' => 'Test Description', 'zh-TW' => '測試描述'],
            'slug' => ['en' => 'test-event', 'zh-TW' => 'test-event-tw'],
            'action_type' => 'show_member_qr',
            'visible_to_membership_levels' => [$this->membershipLevel1->id, $this->membershipLevel2->id],
            'event_status' => 'published',
            'visibility' => 'private',
        ]);

        $event = $this->action->execute($eventData);

        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->action_type)->toBe('show_member_qr')
            ->and($event->visible_to_membership_levels)->toBe([$this->membershipLevel1->id, $this->membershipLevel2->id])
            ->and($event->event_status)->toBe('published')
            ->and($event->visibility)->toBe('private')
            ->and($event->created_by)->toBe($this->user->id)
            ->and($event->updated_by)->toBe($this->user->id);
    });

    it('creates event with purchase_ticket action_type', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Purchase Event', 'zh-TW' => '購買活動'],
            'short_summary' => ['en' => 'Purchase Summary', 'zh-TW' => '購買摘要'],
            'description' => ['en' => 'Purchase Description', 'zh-TW' => '購買描述'],
            'action_type' => 'purchase_ticket',
            'visible_to_membership_levels' => null, // Public event
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        $event = $this->action->execute($eventData);

        expect($event->action_type)->toBe('purchase_ticket')
            ->and($event->visible_to_membership_levels)->toBeNull()
            ->and($event->visibility)->toBe('public');
    });

    it('creates event with null action_type uses default and null membership levels', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Default Event', 'zh-TW' => '預設活動'],
            'short_summary' => ['en' => 'Default Summary', 'zh-TW' => '預設摘要'],
            'description' => ['en' => 'Default Description', 'zh-TW' => '預設描述'],
            'action_type' => null,
            'visible_to_membership_levels' => null,
        ]);

        $event = $this->action->execute($eventData);

        expect($event->action_type)->toBe('purchase_ticket') // Default value from migration
            ->and($event->visible_to_membership_levels)->toBeNull();
    });
});

describe('UpsertEventAction - Update Event', function () {
    it('updates existing event with new action_type and membership levels', function () {
        // Create an existing event
        $existingEvent = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Existing Event'],
            'short_summary' => ['en' => 'Existing Summary'],
            'description' => ['en' => 'Existing Description'],
            'action_type' => 'purchase_ticket',
            'visible_to_membership_levels' => null,
        ]);

        $eventData = EventData::from([
            'id' => $existingEvent->id,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Updated Event', 'zh-TW' => '更新活動'],
            'short_summary' => ['en' => 'Updated Summary', 'zh-TW' => '更新摘要'],
            'description' => ['en' => 'Updated Description', 'zh-TW' => '更新描述'],
            'action_type' => 'show_member_qr',
            'visible_to_membership_levels' => [$this->membershipLevel1->id],
            'event_status' => 'published',
        ]);

        $event = $this->action->execute($eventData);

        expect($event->id)->toBe($existingEvent->id)
            ->and($event->action_type)->toBe('show_member_qr')
            ->and($event->visible_to_membership_levels)->toBe([$this->membershipLevel1->id])
            ->and($event->getTranslation('name', 'en'))->toBe('Updated Event')
            ->and($event->updated_by)->toBe($this->user->id);
    });

    it('updates event from show_member_qr back to purchase_ticket', function () {
        // Create event with show_member_qr action
        $existingEvent = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'QR Event'],
            'short_summary' => ['en' => 'QR Summary'],
            'description' => ['en' => 'QR Description'],
            'action_type' => 'show_member_qr',
            'visible_to_membership_levels' => [$this->membershipLevel1->id, $this->membershipLevel2->id],
        ]);

        $eventData = EventData::from([
            'id' => $existingEvent->id,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'QR Event'],
            'short_summary' => ['en' => 'QR Summary'],
            'description' => ['en' => 'QR Description'],
            'action_type' => 'purchase_ticket',
            'visible_to_membership_levels' => null, // Remove membership restrictions
        ]);

        $event = $this->action->execute($eventData);

        expect($event->action_type)->toBe('purchase_ticket')
            ->and($event->visible_to_membership_levels)->toBeNull();
    });
});

describe('UpsertEventAction - Validation', function () {
    it('throws exception for invalid action_type', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Invalid Action Event'],
            'short_summary' => ['en' => 'Invalid Summary'],
            'description' => ['en' => 'Invalid Description'],
            'action_type' => 'invalid_action',
        ]);

        expect(fn () => $this->action->execute($eventData))
            ->toThrow(InvalidArgumentException::class, 'Invalid action_type \'invalid_action\'');
    });

    it('throws exception for invalid event_status', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Invalid Status Event'],
            'short_summary' => ['en' => 'Invalid Summary'],
            'description' => ['en' => 'Invalid Description'],
            'event_status' => 'invalid_status',
        ]);

        expect(fn () => $this->action->execute($eventData))
            ->toThrow(InvalidArgumentException::class, 'Invalid event_status \'invalid_status\'');
    });

    it('throws exception for invalid visibility', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Invalid Visibility Event'],
            'short_summary' => ['en' => 'Invalid Summary'],
            'description' => ['en' => 'Invalid Description'],
            'visibility' => 'invalid_visibility',
        ]);

        expect(fn () => $this->action->execute($eventData))
            ->toThrow(InvalidArgumentException::class, 'Invalid visibility \'invalid_visibility\'');
    });

    it('throws exception for non-array membership levels', function () {
        // We can't test this through EventData::from() as it enforces type safety
        // Instead we'll test the validation directly in the action
        $this->markTestSkipped('EventData DTO enforces type safety at construction time');
    });

    it('accepts valid action_types', function ($actionType) {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => "Event with {$actionType}"],
            'short_summary' => ['en' => 'Valid Summary'],
            'description' => ['en' => 'Valid Description'],
            'action_type' => $actionType,
        ]);

        $event = $this->action->execute($eventData);

        expect($event->action_type)->toBe($actionType);
    })->with(['purchase_ticket', 'show_member_qr']);
});

describe('UpsertEventAction - Redirect URL', function () {
    it('creates event with redirect_url', function () {
        $redirectUrl = 'https://external-ticketing.example.com/event/123';

        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Redirect Event', 'zh-TW' => '重定向活動'],
            'short_summary' => ['en' => 'Redirect Summary', 'zh-TW' => '重定向摘要'],
            'description' => ['en' => 'Redirect Description', 'zh-TW' => '重定向描述'],
            'action_type' => 'purchase_ticket',
            'redirect_url' => $redirectUrl,
        ]);

        $event = $this->action->execute($eventData);

        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->redirect_url)->toBe($redirectUrl)
            ->and($event->hasRedirectUrl())->toBeTrue()
            ->and($event->getRedirectUrl())->toBe($redirectUrl);
    });

    it('creates event without redirect_url', function () {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Normal Event', 'zh-TW' => '正常活動'],
            'short_summary' => ['en' => 'Normal Summary', 'zh-TW' => '正常摘要'],
            'description' => ['en' => 'Normal Description', 'zh-TW' => '正常描述'],
            'action_type' => 'purchase_ticket',
            'redirect_url' => null,
        ]);

        $event = $this->action->execute($eventData);

        expect($event->redirect_url)->toBeNull()
            ->and($event->hasRedirectUrl())->toBeFalse()
            ->and($event->getRedirectUrl())->toBeNull();
    });

    it('updates event to add redirect_url', function () {
        // Create event without redirect URL
        $existingEvent = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Existing Event'],
            'short_summary' => ['en' => 'Existing Summary'],
            'description' => ['en' => 'Existing Description'],
            'redirect_url' => null,
        ]);

        $redirectUrl = 'https://tickets.example.com/event/456';

        $eventData = EventData::from([
            'id' => $existingEvent->id,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Existing Event'],
            'short_summary' => ['en' => 'Existing Summary'],
            'description' => ['en' => 'Existing Description'],
            'redirect_url' => $redirectUrl,
        ]);

        $event = $this->action->execute($eventData);

        expect($event->id)->toBe($existingEvent->id)
            ->and($event->redirect_url)->toBe($redirectUrl)
            ->and($event->hasRedirectUrl())->toBeTrue();
    });

    it('updates event to remove redirect_url', function () {
        // Create event with redirect URL
        $existingEvent = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Redirect Event'],
            'short_summary' => ['en' => 'Redirect Summary'],
            'description' => ['en' => 'Redirect Description'],
            'redirect_url' => 'https://external.example.com/tickets',
        ]);

        $eventData = EventData::from([
            'id' => $existingEvent->id,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Redirect Event'],
            'short_summary' => ['en' => 'Redirect Summary'],
            'description' => ['en' => 'Redirect Description'],
            'redirect_url' => null,
        ]);

        $event = $this->action->execute($eventData);

        expect($event->id)->toBe($existingEvent->id)
            ->and($event->redirect_url)->toBeNull()
            ->and($event->hasRedirectUrl())->toBeFalse();
    });

    it('accepts various valid redirect_url formats', function ($url) {
        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Valid URL Event'],
            'short_summary' => ['en' => 'Valid URL Summary'],
            'description' => ['en' => 'Valid URL Description'],
            'redirect_url' => $url,
        ]);

        $event = $this->action->execute($eventData);

        expect($event->redirect_url)->toBe($url);
    })->with([
        'https://example.com',
        'http://localhost:3000',
        'https://www.example.com/path/to/tickets',
        'https://tickets.example.com/event/123?utm_source=platform',
        'https://example.com:8080/tickets',
    ]);
});

describe('UpsertEventAction - Database Integrity', function () {
    it('ensures all critical fields are properly saved to database', function () {
        $redirectUrl = 'https://database-test.example.com/tickets';

        $eventData = EventData::from([
            'id' => null,
            'organizer_id' => $this->organizer->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Database Test Event', 'zh-TW' => '資料庫測試活動'],
            'short_summary' => ['en' => 'Database Summary', 'zh-TW' => '資料庫摘要'],
            'description' => ['en' => 'Database Description', 'zh-TW' => '資料庫描述'],
            'action_type' => 'show_member_qr',
            'visible_to_membership_levels' => [$this->membershipLevel1->id, $this->membershipLevel2->id],
            'event_status' => 'published',
            'visibility' => 'private',
            'is_featured' => true,
            'redirect_url' => $redirectUrl,
        ]);

        $event = $this->action->execute($eventData);

        // Re-fetch from database to ensure persistence
        $freshEvent = Event::find($event->id);

        expect($freshEvent->action_type)->toBe('show_member_qr')
            ->and($freshEvent->visible_to_membership_levels)->toBe([$this->membershipLevel1->id, $this->membershipLevel2->id])
            ->and($freshEvent->event_status)->toBe('published')
            ->and($freshEvent->visibility)->toBe('private')
            ->and($freshEvent->is_featured)->toBeTrue()
            ->and($freshEvent->redirect_url)->toBe($redirectUrl);
    });
});
