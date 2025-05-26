<?php

namespace Tests\Unit\Helpers;

use App\Helpers\QrCodeHelper;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeHelperTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_valid_qr_code_format()
    {
        $qrCode = QrCodeHelper::generate();

        $this->assertStringStartsWith('BK-', $qrCode);
        $this->assertEquals(15, strlen($qrCode)); // BK- (3) + 12 characters
        $this->assertTrue(QrCodeHelper::isValidFormat($qrCode));
        $this->assertMatchesRegularExpression('/^BK-[A-Z0-9]{12}$/', $qrCode);
    }

    /** @test */
    public function it_generates_unique_qr_codes()
    {
        // Create some existing bookings with QR codes
        Booking::factory()->withQrCode('BK-EXISTING0001')->create();
        Booking::factory()->withQrCode('BK-EXISTING0002')->create();

        $qrCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $qrCode = QrCodeHelper::generate();
            $this->assertNotContains($qrCode, $qrCodes, 'Generated QR code should be unique');
            $this->assertNotEquals('BK-EXISTING0001', $qrCode);
            $this->assertNotEquals('BK-EXISTING0002', $qrCode);
            $qrCodes[] = $qrCode;
        }
    }

    /** @test */
    public function it_validates_qr_code_format_correctly()
    {
        // Valid formats
        $this->assertTrue(QrCodeHelper::isValidFormat('BK-ABC123DEF456'));
        $this->assertTrue(QrCodeHelper::isValidFormat('BK-123456789012'));
        $this->assertTrue(QrCodeHelper::isValidFormat('BK-ABCDEFGHIJKL'));

        // Invalid formats
        $this->assertFalse(QrCodeHelper::isValidFormat('ABC123DEF456')); // Missing BK- prefix
        $this->assertFalse(QrCodeHelper::isValidFormat('BK-ABC123')); // Too short
        $this->assertFalse(QrCodeHelper::isValidFormat('BK-ABC123DEF4567')); // Too long
        $this->assertFalse(QrCodeHelper::isValidFormat('bk-abc123def456')); // Lowercase prefix
        $this->assertFalse(QrCodeHelper::isValidFormat('BK-abc123def456')); // Lowercase suffix
        $this->assertFalse(QrCodeHelper::isValidFormat('')); // Empty
        $this->assertFalse(QrCodeHelper::isValidFormat('BK-ABC123-DEF456')); // Extra dash
        $this->assertFalse(QrCodeHelper::isValidFormat('BK-ABC123DEF45@')); // Special character
    }

    /** @test */
    public function it_extracts_suffix_correctly()
    {
        $this->assertEquals('ABC123DEF456', QrCodeHelper::extractSuffix('BK-ABC123DEF456'));
        $this->assertEquals('123456789012', QrCodeHelper::extractSuffix('BK-123456789012'));

        // Invalid formats should return null
        $this->assertNull(QrCodeHelper::extractSuffix('INVALID-FORMAT'));
        $this->assertNull(QrCodeHelper::extractSuffix('BK-TOOSHORT'));
        $this->assertNull(QrCodeHelper::extractSuffix(''));
    }

    /** @test */
    public function it_creates_qr_code_from_suffix()
    {
        $this->assertEquals('BK-ABC123DEF456', QrCodeHelper::fromSuffix('ABC123DEF456'));
        $this->assertEquals('BK-123456789012', QrCodeHelper::fromSuffix('123456789012'));
        $this->assertEquals('BK-ABCDEFGHIJKL', QrCodeHelper::fromSuffix('abcdefghijkl')); // Should uppercase

        // Invalid suffixes should return null
        $this->assertNull(QrCodeHelper::fromSuffix('TOOSHORT')); // Too short
        $this->assertNull(QrCodeHelper::fromSuffix('TOOLONGSTRING')); // Too long
        $this->assertNull(QrCodeHelper::fromSuffix('ABC123DEF45@')); // Special character
        $this->assertNull(QrCodeHelper::fromSuffix('')); // Empty
    }

    /** @test */
    public function it_returns_correct_pattern()
    {
        $pattern = QrCodeHelper::getPattern();
        $this->assertEquals('/^BK-[A-Z0-9]{12}$/', $pattern);

        // Test that the pattern works
        $this->assertMatchesRegularExpression($pattern, 'BK-ABC123DEF456');
        $this->assertDoesNotMatchRegularExpression($pattern, 'INVALID-FORMAT');
    }

    /** @test */
    public function it_returns_format_description()
    {
        $description = QrCodeHelper::getFormatDescription();
        $this->assertStringContainsString('BK-', $description);
        $this->assertStringContainsString('12', $description);
        $this->assertStringContainsString('alphanumeric', $description);
    }

    /** @test */
    public function it_generates_batch_of_unique_qr_codes()
    {
        // Create some existing bookings
        Booking::factory()->withQrCode('BK-EXISTING0001')->create();
        Booking::factory()->withQrCode('BK-EXISTING0002')->create();

        $batchSize = 5;
        $qrCodes = QrCodeHelper::generateBatch($batchSize);

        $this->assertCount($batchSize, $qrCodes);

        // All should be unique
        $this->assertEquals($batchSize, count(array_unique($qrCodes)));

        // All should be valid format
        foreach ($qrCodes as $qrCode) {
            $this->assertTrue(QrCodeHelper::isValidFormat($qrCode));
        }

        // None should match existing codes
        $this->assertNotContains('BK-EXISTING0001', $qrCodes);
        $this->assertNotContains('BK-EXISTING0002', $qrCodes);
    }

    /** @test */
    public function it_has_correct_constants()
    {
        $this->assertEquals('BK-', QrCodeHelper::PREFIX);
        $this->assertEquals(12, QrCodeHelper::SUFFIX_LENGTH);
        $this->assertEquals(15, QrCodeHelper::TOTAL_LENGTH);
        $this->assertEquals('/^BK-[A-Z0-9]{12}$/', QrCodeHelper::PATTERN);
    }

    /** @test */
    public function generated_qr_codes_pass_validation()
    {
        for ($i = 0; $i < 20; $i++) {
            $qrCode = QrCodeHelper::generate();
            $this->assertTrue(QrCodeHelper::isValidFormat($qrCode), "Generated QR code '$qrCode' should pass validation");
        }
    }
}
