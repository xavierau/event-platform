<?php

namespace App\DataTransferObjects;

use App\Enums\CheckInMethod;
use App\Enums\RoleNameEnum;
use App\Helpers\QrCodeHelper;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class CheckInData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public readonly string $qr_code_identifier,

        #[Required, Exists('event_occurrences', 'id')]
        public readonly int $event_occurrence_id,

        #[Required, In(CheckInMethod::class)]
        public readonly CheckInMethod $method,

        #[Nullable, StringType, Max(255)]
        public readonly ?string $device_identifier,

        #[Nullable, StringType, Max(255)]
        public readonly ?string $location_description,

        #[Nullable, Exists('users', 'id')]
        public readonly ?int $operator_user_id,

        #[Nullable, StringType, Max(1000)]
        public readonly ?string $notes,
    ) {}

    public static function rules(): array
    {
        return [
            'qr_code_identifier' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!QrCodeHelper::isValidFormat($value)) {
                        $fail('The QR code format is invalid. ' . QrCodeHelper::getFormatDescription());
                    }
                },
            ],
            'event_occurrence_id' => [
                'required',
                'integer',
                'exists:event_occurrences,id',
                function ($attribute, $value, $fail) {
                    // Validate that the event occurrence belongs to the same event as the booking
                    $qrCode = request()->input('qr_code_identifier');
                    if ($qrCode && $value) {
                        $booking = \App\Models\Booking::byQrCode($qrCode)->first();
                        if ($booking) {
                            $eventOccurrence = \App\Models\EventOccurrence::find($value);
                            if ($eventOccurrence && $booking->event_id !== $eventOccurrence->event_id) {
                                $fail('The event occurrence must belong to the same event as the booking.');
                            }
                        }
                    }
                },
            ],
            'method' => ['required', 'string', 'in:' . implode(',', array_map(fn($case) => $case->value, CheckInMethod::cases()))],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'location_description' => ['nullable', 'string', 'max:255'],
            'operator_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value !== null) {
                        $user = \App\Models\User::find($value);
                        if (!$user) {
                            $fail('The operator user does not exist.');
                            return;
                        }

                        // Platform admins can check in any booking
                        if ($user->hasRole(RoleNameEnum::ADMIN)) {
                            return [];
                        }

                        // Check if user has organizer entity membership for this event's organizer
                        $booking = \App\Models\Booking::byQrCode(request()->input('qr_code_identifier'))->first();
                        if (!$booking) {
                            $fail('Cannot validate operator permissions: booking not found.');
                            return;
                        }

                        $userOrganizerIds = \App\Models\Organizer::whereHas('users', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        })->pluck('organizers.id');

                        if (!$userOrganizerIds->contains($booking->event->organizer_id)) {
                            $fail('You are not authorized to check in for this event.');
                            return;
                        }
                    }
                },
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
