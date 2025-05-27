<?php

namespace App\DataTransferObjects;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class WishlistData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(1), Exists('users', 'id')]
        public readonly int $user_id,

        #[Required, IntegerType, Min(1)]
        public readonly int $event_id,
    ) {}

    public static function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'min:1',
                'exists:users,id',
            ],
            'event_id' => [
                'required',
                'integer',
                'min:1',
                'exists:events,id',
                function ($attribute, $value, $fail) {
                    // Validate that the event is published (active)
                    $event = \App\Models\Event::find($value);
                    if ($event && $event->event_status !== 'published') {
                        $fail('The event must be published to be added to wishlist.');
                    }
                },
            ],
        ];
    }
}
