<?php

namespace App\ValueObjects;

use App\Models\MemberCheckIn;
use App\Models\User;

class CheckInResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?string $message = null,
        private readonly ?MemberCheckIn $checkIn = null,
        private readonly ?User $member = null,
        private readonly ?array $membershipData = null,
    ) {}

    public static function success(
        MemberCheckIn $checkIn,
        string $message = 'Check-in successful'
    ): self {
        return new self(
            success: true,
            message: $message,
            checkIn: $checkIn,
            member: $checkIn->member,
            membershipData: $checkIn->membership_data
        );
    }

    public static function validationSuccess(
        User $member,
        array $membershipData,
        string $message = 'Member QR validation successful'
    ): self {
        return new self(
            success: true,
            message: $message,
            member: $member,
            membershipData: $membershipData
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            success: false,
            message: $message
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCheckIn(): ?MemberCheckIn
    {
        return $this->checkIn;
    }

    public function getMember(): ?User
    {
        return $this->member;
    }

    public function getMembershipData(): ?array
    {
        return $this->membershipData;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'check_in' => $this->checkIn?->toArray(),
            'member' => $this->member?->toArray(),
            'membership_data' => $this->membershipData,
        ];
    }
}