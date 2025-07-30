<?php

namespace App\DataTransferObjects;

use App\Enums\RoleNameEnum;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class MemberCheckInData extends Data
{
    public function __construct(
        #[Required, Exists('users', 'id')]
        public readonly int $user_id,

        #[Required, Exists('users', 'id')]
        public readonly int $scanned_by_user_id,

        #[Required, StringType]
        public readonly string $scanned_at,

        #[Nullable, StringType, Max(255)]
        public readonly ?string $location,

        #[Nullable, StringType, Max(1000)]
        public readonly ?string $notes,

        #[Nullable, StringType, Max(255)]
        public readonly ?string $device_identifier,

        #[Nullable]
        public readonly ?array $membership_data,
    ) {}

    public static function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    // Validate that the user exists and has a valid membership
                    $user = \App\Models\User::with('currentMembership')->find($value);
                    if (!$user) {
                        $fail('The member user does not exist.');
                        return;
                    }
                },
            ],
            'scanned_by_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $scanner = \App\Models\User::find($value);
                    if (!$scanner) {
                        $fail('The scanner user does not exist.');
                        return;
                    }

                    // Platform admins can scan any member
                    if ($scanner->hasRole(RoleNameEnum::ADMIN)) {
                        return;
                    }

                    // Check if user has organizer entity membership (same permissions as QR scanner)
                    $userOrganizerIds = \App\Models\Organizer::whereHas('users', function ($query) use ($scanner) {
                        $query->where('user_id', $scanner->id);
                    })->pluck('id');

                    if ($userOrganizerIds->isEmpty()) {
                        $fail('You are not authorized to perform member check-ins.');
                        return;
                    }
                },
            ],
            'scanned_at' => [
                'required',
                'string',
                'date_format:Y-m-d H:i:s',
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'membership_data' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if ($value !== null) {
                        // Validate membership data structure from MyProfile.vue QR
                        $requiredFields = ['userId', 'userName', 'email', 'membershipLevel'];
                        foreach ($requiredFields as $field) {
                            if (!isset($value[$field])) {
                                $fail("The membership data is missing required field: {$field}");
                                return;
                            }
                        }

                        // Validate that userId matches the user_id
                        if (isset($value['userId']) && $value['userId'] != request()->input('user_id')) {
                            $fail('The membership data userId does not match the user_id.');
                            return;
                        }
                    }
                },
            ],
        ];
    }
}