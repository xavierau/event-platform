<?php

use App\Modules\Coupon\Actions\GenerateUniqueCodeAction;
use App\Modules\Coupon\Models\UserCoupon;

beforeEach(function () {
    $this->action = new GenerateUniqueCodeAction();
});

describe('GenerateUniqueCodeAction', function () {

    test('generates a unique code with correct format', function () {
        $code = $this->action->execute();

        expect($code)
            ->toBeString()
            ->toHaveLength(12) // Expected format: ABC123DEF456
            ->toMatch('/^[A-Z0-9]{12}$/'); // Only uppercase letters and numbers
    });

    test('generates different codes on multiple calls', function () {
        $code1 = $this->action->execute();
        $code2 = $this->action->execute();
        $code3 = $this->action->execute();

        expect($code1)->not->toBe($code2)
            ->and($code2)->not->toBe($code3)
            ->and($code1)->not->toBe($code3);
    });

    test('generates code that does not already exist in database', function () {
        // Create an existing UserCoupon with a known code
        UserCoupon::factory()->create([
            'unique_code' => 'EXISTING123'
        ]);

        $newCode = $this->action->execute();

        expect($newCode)->not->toBe('EXISTING123');

        // Verify it doesn't exist in database
        expect(UserCoupon::where('unique_code', $newCode)->exists())->toBeFalse();
    });

    test('handles collision by generating new code', function () {
        // Mock the random generation to force a collision initially
        $action = new class extends GenerateUniqueCodeAction {
            private int $callCount = 0;

            protected function generateRandomCode(): string
            {
                $this->callCount++;
                // Force collision on first call, then return unique code
                return $this->callCount === 1 ? 'COLLISION12' : 'UNIQUE12345';
            }
        };

        // Create a UserCoupon with the collision code
        UserCoupon::factory()->create([
            'unique_code' => 'COLLISION12'
        ]);

        $code = $action->execute();

        expect($code)->toBe('UNIQUE12345');
    });

    test('generates codes that are URL safe', function () {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->action->execute();
        }

        foreach ($codes as $code) {
            expect($code)
                ->toMatch('/^[A-Z0-9]+$/')  // Only alphanumeric uppercase
                ->not->toContain('+')       // No URL problematic characters
                ->not->toContain('/')
                ->not->toContain('=')
                ->not->toContain(' ');
        }
    });

    test('generates codes suitable for QR codes', function () {
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = $this->action->execute();
        }

        foreach ($codes as $code) {
            // Should be easily readable in QR codes
            expect($code)
                ->toHaveLength(12)  // Good balance of uniqueness vs QR code size
                ->toMatch('/^[A-Z0-9]+$/') // QR code friendly characters
                ->not->toContain('0')      // Avoid confusing characters
                ->not->toContain('O')
                ->not->toContain('I')
                ->not->toContain('1');
        }
    });

    test('performance test - generates 100 unique codes quickly', function () {
        $startTime = microtime(true);
        $codes = [];

        for ($i = 0; $i < 100; $i++) {
            $codes[] = $this->action->execute();
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete in under 1 second
        expect($duration)->toBeLessThan(1.0);

        // All codes should be unique
        expect(array_unique($codes))->toHaveCount(100);
    });

    test('prevents infinite loop in extreme collision scenario', function () {
        $action = new class extends GenerateUniqueCodeAction {
            protected int $maxAttempts = 3; // Override for testing

            protected function generateRandomCode(): string
            {
                return 'SAMECODE123'; // Always return same code to force collision
            }
        };

        // Create a UserCoupon with the code that will always be generated
        UserCoupon::factory()->create([
            'unique_code' => 'SAMECODE123'
        ]);

        // Should throw exception after max attempts
        expect(fn() => $action->execute())
            ->toThrow(\Exception::class, 'Unable to generate unique code after maximum attempts');
    });
});
