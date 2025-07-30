<?php

use App\Contracts\MemberQrValidatorInterface;
use App\DataTransferObjects\MemberCheckInData;
use App\Models\MemberCheckIn;
use App\Models\User;
use App\Services\MemberCheckInService;
use App\ValueObjects\CheckInResult;
use App\ValueObjects\ValidationResult;

describe('MemberCheckInService', function () {
    beforeEach(function () {
        $this->mockValidator = Mockery::mock(MemberQrValidatorInterface::class);
        $this->service = new MemberCheckInService($this->mockValidator);
        
        $this->member = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        $this->scanner = User::factory()->create([
            'name' => 'Admin Scanner',
            'email' => 'admin@example.com',
        ]);
    });

    afterEach(function () {
        Mockery::close();
    });

    describe('processCheckIn method', function () {
        it('successfully processes valid member check-in', function () {
            $qrCode = json_encode([
                'userId' => $this->member->id,
                'userName' => $this->member->name,
                'email' => $this->member->email,
                'membershipLevel' => 'Premium',
                'membershipStatus' => 'Active',
                'timestamp' => now()->toISOString(),
            ]);

            $context = [
                'scanner_id' => $this->scanner->id,
                'location' => 'Main Entrance',
                'device_identifier' => 'SCANNER-001',
            ];

            // Mock successful validation
            $this->mockValidator->shouldReceive('validate')
                ->once()
                ->with($qrCode)
                ->andReturn(ValidationResult::success($this->member, json_decode($qrCode, true)));

            $result = $this->service->processCheckIn($qrCode, $context);

            expect($result)->toBeInstanceOf(CheckInResult::class);
            expect($result->isSuccess())->toBeTrue();
            expect($result->getMessage())->toBe('Member check-in successful');
            expect($result->getCheckIn())->toBeInstanceOf(MemberCheckIn::class);
            expect($result->getMember()->id)->toBe($this->member->id);
        });

        it('fails with invalid QR code', function () {
            $invalidQrCode = 'invalid-qr-code';
            $context = ['scanner_id' => $this->scanner->id];

            // Mock validation failure
            $this->mockValidator->shouldReceive('validate')
                ->once()
                ->with($invalidQrCode)
                ->andReturn(ValidationResult::failure('Invalid QR code format'));

            $result = $this->service->processCheckIn($invalidQrCode, $context);

            expect($result->isSuccess())->toBeFalse();
            expect($result->getMessage())->toBe('Invalid QR code format');
            expect($result->getCheckIn())->toBeNull();
        });

        it('fails when scanner user is missing', function () {
            $qrCode = json_encode(['userId' => $this->member->id]);
            $context = []; // No scanner_id

            $result = $this->service->processCheckIn($qrCode, $context);

            expect($result->isSuccess())->toBeFalse();
            expect($result->getMessage())->toBe('Scanner user ID is required');
        });

        it('logs check-in with correct data', function () {
            $qrCode = json_encode([
                'userId' => $this->member->id,
                'userName' => $this->member->name,
                'membershipLevel' => 'Premium',
            ]);

            $context = [
                'scanner_id' => $this->scanner->id,
                'location' => 'VIP Lounge',
                'notes' => 'Premium member check-in',
            ];

            $this->mockValidator->shouldReceive('validate')
                ->once()
                ->andReturn(ValidationResult::success($this->member, json_decode($qrCode, true)));

            $result = $this->service->processCheckIn($qrCode, $context);

            expect($result->isSuccess())->toBeTrue();
            
            $checkIn = $result->getCheckIn();
            expect($checkIn->user_id)->toBe($this->member->id);
            expect($checkIn->scanned_by_user_id)->toBe($this->scanner->id);
            expect($checkIn->location)->toBe('VIP Lounge');
            expect($checkIn->notes)->toBe('Premium member check-in');
        });
    });

    describe('validateMemberQr method', function () {
        it('returns success for valid QR code', function () {
            $qrCode = json_encode([
                'userId' => $this->member->id,
                'userName' => $this->member->name,
                'membershipLevel' => 'Standard',
            ]);

            $membershipData = json_decode($qrCode, true);

            $this->mockValidator->shouldReceive('validate')
                ->once()
                ->with($qrCode)
                ->andReturn(ValidationResult::success($this->member, $membershipData));

            $result = $this->service->validateMemberQr($qrCode);

            expect($result->isSuccess())->toBeTrue();
            expect($result->getMember()->id)->toBe($this->member->id);
            expect($result->getMembershipData()['membershipLevel'])->toBe('Standard');
        });

        it('returns failure for invalid QR code', function () {
            $invalidQrCode = 'invalid';

            $this->mockValidator->shouldReceive('validate')
                ->once()
                ->with($invalidQrCode)
                ->andReturn(ValidationResult::failure('Invalid format'));

            $result = $this->service->validateMemberQr($invalidQrCode);

            expect($result->isSuccess())->toBeFalse();
            expect($result->getMessage())->toBe('Invalid format');
        });
    });

    describe('logCheckIn method', function () {
        it('creates member check-in record', function () {
            $data = MemberCheckInData::from([
                'user_id' => $this->member->id,  
                'scanned_by_user_id' => $this->scanner->id,
                'scanned_at' => now()->format('Y-m-d H:i:s'),
                'location' => 'Conference Hall',
                'notes' => 'Test check-in',
                'device_identifier' => 'SCANNER-002',
                'membership_data' => [
                    'userId' => $this->member->id,
                    'membershipLevel' => 'VIP',
                ],
            ]);

            $checkIn = $this->service->logCheckIn($data);

            expect($checkIn)->toBeInstanceOf(MemberCheckIn::class);
            expect($checkIn->user_id)->toBe($this->member->id);
            expect($checkIn->scanned_by_user_id)->toBe($this->scanner->id);
            expect($checkIn->location)->toBe('Conference Hall');
            expect($checkIn->membership_data['membershipLevel'])->toBe('VIP');
        });
    });

    describe('getCheckInHistory method', function () {
        it('returns check-in history for member', function () {
            // Create some check-ins
            MemberCheckIn::factory()
                ->forMember($this->member)
                ->count(3)
                ->create();

            $history = $this->service->getCheckInHistory($this->member);

            expect($history)->toBeArray();
            expect($history)->toHaveCount(3);
            expect($history[0])->toHaveKey('scanned_at');
            expect($history[0])->toHaveKey('location');
            expect($history[0])->toHaveKey('scanner_name');
        });

        it('limits check-in history results', function () {
            MemberCheckIn::factory()
                ->forMember($this->member)
                ->count(10)
                ->create();

            $history = $this->service->getCheckInHistory($this->member, 5);

            expect($history)->toHaveCount(5);
        });
    });

    describe('getRecentCheckInsByScanner method', function () {
        it('returns recent check-ins by scanner', function () {
            // Create recent and old check-ins
            MemberCheckIn::factory()
                ->scannedBy($this->scanner)
                ->create(['scanned_at' => now()->subHours(2)]);

            MemberCheckIn::factory()
                ->scannedBy($this->scanner)
                ->create(['scanned_at' => now()->subDays(2)]);

            $recentCheckIns = $this->service->getRecentCheckInsByScanner($this->scanner, 24);

            expect($recentCheckIns)->toHaveCount(1);
            expect($recentCheckIns[0])->toHaveKey('member_name');
            expect($recentCheckIns[0])->toHaveKey('scanned_at');
        });
    });
});