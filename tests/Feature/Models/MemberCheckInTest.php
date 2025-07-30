<?php

use App\Models\MemberCheckIn;
use App\Models\User;

describe('MemberCheckIn Model', function () {
    beforeEach(function () {
        $this->member = User::factory()->create();
        $this->scanner = User::factory()->create();
    });

    it('can create a member check-in', function () {
        $checkIn = MemberCheckIn::factory()
            ->forMember($this->member)
            ->scannedBy($this->scanner)
            ->create();

        expect($checkIn)->toBeInstanceOf(MemberCheckIn::class);
        expect($checkIn->user_id)->toBe($this->member->id);
        expect($checkIn->scanned_by_user_id)->toBe($this->scanner->id);
        expect($checkIn->scanned_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('belongs to a member', function () {
        $checkIn = MemberCheckIn::factory()
            ->forMember($this->member)
            ->create();

        expect($checkIn->member)->toBeInstanceOf(User::class);
        expect($checkIn->member->id)->toBe($this->member->id);
    });

    it('belongs to a scanner', function () {
        $checkIn = MemberCheckIn::factory()
            ->scannedBy($this->scanner)
            ->create();

        expect($checkIn->scanner)->toBeInstanceOf(User::class);
        expect($checkIn->scanner->id)->toBe($this->scanner->id);
    });

    it('casts membership_data to array', function () {
        $membershipData = [
            'userId' => $this->member->id,
            'userName' => $this->member->name,
            'email' => $this->member->email,
            'membershipLevel' => 'Premium',
            'membershipStatus' => 'Active',
        ];

        $checkIn = MemberCheckIn::factory()->create([
            'membership_data' => $membershipData,
        ]);

        expect($checkIn->membership_data)->toBeArray();
        expect($checkIn->membership_data['userId'])->toBe($this->member->id);
        expect($checkIn->membership_data['membershipLevel'])->toBe('Premium');
    });

    it('can scope check-ins for a specific member', function () {
        $otherMember = User::factory()->create();
        
        MemberCheckIn::factory()
            ->forMember($this->member)
            ->count(3)
            ->create();
            
        MemberCheckIn::factory()
            ->forMember($otherMember)
            ->count(2)
            ->create();

        $memberCheckIns = MemberCheckIn::forMember($this->member)->get();
        
        expect($memberCheckIns)->toHaveCount(3);
        $memberCheckIns->each(function ($checkIn) {
            expect($checkIn->user_id)->toBe($this->member->id);
        });
    });

    it('can scope check-ins by scanner', function () {
        $otherScanner = User::factory()->create();
        
        MemberCheckIn::factory()
            ->scannedBy($this->scanner)
            ->count(2)
            ->create();
            
        MemberCheckIn::factory()
            ->scannedBy($otherScanner)
            ->count(3)
            ->create();

        $scannerCheckIns = MemberCheckIn::byScanner($this->scanner)->get();
        
        expect($scannerCheckIns)->toHaveCount(2);
        $scannerCheckIns->each(function ($checkIn) {
            expect($checkIn->scanned_by_user_id)->toBe($this->scanner->id);
        });
    });

    it('can scope check-ins in date range', function () {
        // Create check-ins at different dates
        MemberCheckIn::factory()->create([
            'scanned_at' => now()->subDays(5)
        ]);
        
        MemberCheckIn::factory()->create([
            'scanned_at' => now()->subDays(2)
        ]);
        
        MemberCheckIn::factory()->create([
            'scanned_at' => now()->addDays(1)
        ]);

        $checkInsInRange = MemberCheckIn::inDateRange(
            now()->subDays(3),
            now()
        )->get();
        
        expect($checkInsInRange)->toHaveCount(1);
    });

    it('can scope recent check-ins', function () {
        // Create check-ins at different times
        MemberCheckIn::factory()->create([
            'scanned_at' => now()->subHours(2)
        ]);
        
        MemberCheckIn::factory()->create([
            'scanned_at' => now()->subDays(2)
        ]);

        $recentCheckIns = MemberCheckIn::recent(24)->get();
        
        expect($recentCheckIns)->toHaveCount(1);
    });

    it('has correct fillable attributes', function () {
        $checkIn = new MemberCheckIn();
        
        $expectedFillable = [
            'user_id',
            'scanned_by_user_id',
            'scanned_at',
            'location',
            'notes',
            'device_identifier',
            'membership_data',
        ];
        
        expect($checkIn->getFillable())->toBe($expectedFillable);
    });

    it('uses correct table name', function () {
        $checkIn = new MemberCheckIn();
        
        expect($checkIn->getTable())->toBe('member_check_ins');
    });
});