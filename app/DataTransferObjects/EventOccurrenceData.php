<?php

namespace App\DataTransferObjects;

use App\Enums\OccurrenceStatus;
use Carbon\Carbon;
// use Illuminate\Http\Request; // Not needed if not using fromRequest override
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
// use Spatie\LaravelData\Attributes\Validation\Sometimes; // Not strictly needed if nullable and date_format handles optional
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
// use Spatie\LaravelData\Casts\ArrayObjectOrArrayCast; // For metadata if needed

#[MapName(SnakeCaseMapper::class)]
class EventOccurrenceData extends Data
{
    public function __construct(
        public readonly int $event_id,
        public readonly array $name, // Translatable: ['en' => 'English Name', ...]
        #[Nullable]
        public readonly ?array $description, // Translatable: ['en' => 'English Desc', ...]
        public readonly ?string $start_at, // Changed to string
        #[AfterOrEqual('start_at')] // Should still work with parseable date strings
        public readonly ?string $end_at, // Changed to string
        #[Nullable]
        public readonly ?int $venue_id,
        public readonly bool $is_online,
        #[Nullable, RequiredIf('is_online', true), Url]
        public readonly ?string $online_meeting_link,
        #[Nullable, Min(0)]
        public readonly ?int $capacity,
        // #[In(OccurrenceStatus::class)] // Redundant if using Enum type hint directly
        public readonly OccurrenceStatus $status, // PHP 8.1+ Enum type hint is sufficient for In validation
        public readonly string $timezone,
        public readonly ?int $id = null, // For existing occurrence ID during updates
    ) {}

    public static function rules(): array
    {
        $rules = [
            'event_id' => 'required|exists:events,id',
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'description' => 'nullable|array',
            'start_at' => 'required|date_format:Y-m-d\TH:i', // Validates incoming string format
            'end_at' => 'required|date_format:Y-m-d\TH:i|after_or_equal:start_at',
            'venue_id' => 'nullable|required_if:is_online,false|exists:venues,id',
            'is_online' => 'required|boolean',
            'online_meeting_link' => 'nullable|required_if:is_online,true|url',
            'capacity' => 'nullable|integer|min:0',
            'status' => ['required', new Enum(OccurrenceStatus::class)],
            'timezone' => 'required|string|timezone:all', // Use timezone:all for general timezones
        ];

        $availableLocales = config('app.available_locales', ['en' => 'English']);
        foreach (array_keys($availableLocales) as $localeCode) {
            if ($localeCode !== 'en') {
                $rules["name.{$localeCode}"] = 'nullable|string|max:255';
                $rules["description.{$localeCode}"] = 'nullable|string';
            }
        }
        return $rules;
    }

    // Removed custom fromRequest method, relying on Spatie\LaravelData default hydration and casts.
}
