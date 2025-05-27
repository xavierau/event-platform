<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\WishlistData;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WishlistDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_created_with_valid_data()
    {
        $data = new WishlistData(
            user_id: 1,
            event_id: 2
        );

        $this->assertEquals(1, $data->user_id);
        $this->assertEquals(2, $data->event_id);
    }

    #[Test]
    public function it_can_be_created_from_array_with_valid_database_records()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $array = [
            'user_id' => $user->id,
            'event_id' => $event->id,
        ];

        $data = WishlistData::from($array);

        $this->assertEquals($user->id, $data->user_id);
        $this->assertEquals($event->id, $data->event_id);
    }

    #[Test]
    public function it_can_be_converted_to_array()
    {
        $data = new WishlistData(
            user_id: 1,
            event_id: 2
        );

        $array = $data->toArray();

        $this->assertEquals([
            'user_id' => 1,
            'event_id' => 2,
        ], $array);
    }

    #[Test]
    public function it_validates_user_exists_in_database()
    {
        $event = Event::factory()->create(['event_status' => 'published']);

        $this->expectException(ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => 999, // Non-existent user
            'event_id' => $event->id,
        ]);
    }

    #[Test]
    public function it_validates_event_exists_in_database()
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => $user->id,
            'event_id' => 999, // Non-existent event
        ]);
    }

    #[Test]
    public function it_validates_event_is_published()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'draft']); // Not published

        $this->expectException(ValidationException::class);

        WishlistData::validateAndCreate([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }

    #[Test]
    public function it_allows_published_events()
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['event_status' => 'published']);

        $data = WishlistData::from([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        $this->assertEquals($user->id, $data->user_id);
        $this->assertEquals($event->id, $data->event_id);
    }
}
