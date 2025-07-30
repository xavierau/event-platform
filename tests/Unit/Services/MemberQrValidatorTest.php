<?php

use App\Models\User;
use App\Services\MemberQrValidator;
use App\ValueObjects\ValidationResult;

describe('MemberQrValidator', function () {
    beforeEach(function () {
        $this->validator = new MemberQrValidator();
        $this->user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    });

    describe('validate method', function () {
        it('validates correct membership QR code', function () {
            $qrData = json_encode([
                'userId' => $this->user->id,
                'userName' => $this->user->name,
                'email' => $this->user->email,
                'membershipLevel' => 'Premium',
                'membershipStatus' => 'Active',
                'expiresAt' => 'December 31, 2025',
                'timestamp' => now()->toISOString(),
            ]);

            $result = $this->validator->validate($qrData);

            expect($result)->toBeInstanceOf(ValidationResult::class);
            expect($result->isValid())->toBeTrue();
            expect($result->getUser()->id)->toBe($this->user->id);
            expect($result->getData()['membershipLevel'])->toBe('Premium');
        });

        it('rejects QR with invalid JSON format', function () {
            $invalidJson = 'not-valid-json';

            $result = $this->validator->validate($invalidJson);

            expect($result->isValid())->toBeFalse();
            expect($result->getError())->toBe('Invalid QR code format');
        });

        it('rejects QR with missing userId', function () {
            $qrData = json_encode([
                'userName' => 'John Doe',
                'email' => 'john@example.com',
                'membershipLevel' => 'Premium',
            ]);

            $result = $this->validator->validate($qrData);

            expect($result->isValid())->toBeFalse();
            expect($result->getError())->toBe('Missing required fields');
        });

        it('rejects QR with missing userName', function () {
            $qrData = json_encode([
                'userId' => $this->user->id,
                'email' => 'john@example.com',
                'membershipLevel' => 'Premium',
            ]);

            $result = $this->validator->validate($qrData);

            expect($result->isValid())->toBeFalse();
            expect($result->getError())->toBe('Missing required fields');
        });

        it('rejects QR with missing email', function () {
            $qrData = json_encode([
                'userId' => $this->user->id,
                'userName' => 'John Doe',
                'membershipLevel' => 'Premium',
            ]);

            $result = $this->validator->validate($qrData);

            expect($result->isValid())->toBeFalse();
            expect($result->getError())->toBe('Missing required fields');
        });

        it('rejects QR with non-existent user ID', function () {
            $qrData = json_encode([
                'userId' => 99999,
                'userName' => 'Non Existent',
                'email' => 'nonexistent@example.com',
                'membershipLevel' => 'Premium',
                'timestamp' => now()->toISOString(),
            ]);

            $result = $this->validator->validate($qrData);

            expect($result->isValid())->toBeFalse();
            expect($result->getError())->toBe('User not found');
        });

        it('rejects expired QR code', function () {
            $qrData = json_encode([
                'userId' => $this->user->id,
                'userName' => $this->user->name,
                'email' => $this->user->email,
                'membershipLevel' => 'Premium',
                'timestamp' => now()->subHours(25)->toISOString(), // 25 hours ago
            ]);

            $result = $this->validator->validate($qrData);

            expect($result->isValid())->toBeFalse();
            expect($result->getError())->toBe('QR code expired');
        });
    });

    describe('isValidFormat method', function () {
        it('returns true for valid JSON', function () {
            $validJson = json_encode(['key' => 'value']);
            
            expect($this->validator->isValidFormat($validJson))->toBeTrue();
        });

        it('returns false for invalid JSON', function () {
            $invalidJson = 'not-json';
            
            expect($this->validator->isValidFormat($invalidJson))->toBeFalse();
        });
    });

    describe('hasRequiredFields method', function () {
        it('returns true when all required fields are present', function () {
            $data = [
                'userId' => 1,
                'userName' => 'John Doe',
                'email' => 'john@example.com',
                'membershipLevel' => 'Premium',
            ];

            expect($this->validator->hasRequiredFields($data))->toBeTrue();
        });

        it('returns false when userId is missing', function () {
            $data = [
                'userName' => 'John Doe',
                'email' => 'john@example.com',
                'membershipLevel' => 'Premium',
            ];

            expect($this->validator->hasRequiredFields($data))->toBeFalse();
        });

        it('returns false when userName is missing', function () {
            $data = [
                'userId' => 1,
                'email' => 'john@example.com',
                'membershipLevel' => 'Premium',
            ];

            expect($this->validator->hasRequiredFields($data))->toBeFalse();
        });
    });

    describe('isNotExpired method', function () {
        it('returns true for recent timestamp', function () {
            $data = ['timestamp' => now()->toISOString()];
            
            expect($this->validator->isNotExpired($data, 24))->toBeTrue();
        });

        it('returns false for expired timestamp', function () {
            $data = ['timestamp' => now()->subHours(25)->toISOString()];
            
            expect($this->validator->isNotExpired($data, 24))->toBeFalse();
        });

        it('returns true when no timestamp is provided', function () {
            $data = [];
            
            expect($this->validator->isNotExpired($data, 24))->toBeTrue();
        });
    });

    describe('hasValidMembership method', function () {
        it('returns true for user without membership (allows basic access)', function () {
            $user = User::factory()->create();
            
            expect($this->validator->hasValidMembership($user))->toBeTrue();
        });

        it('returns true for user with active membership', function () {
            $user = User::factory()->create();
            // Create active membership
            \App\Modules\Membership\Models\UserMembership::factory()->create([
                'user_id' => $user->id,
                'status' => \App\Modules\Membership\Enums\MembershipStatus::ACTIVE,
                'expires_at' => now()->addMonth(),
            ]);

            expect($this->validator->hasValidMembership($user))->toBeTrue();
        });
    });
});