<?php

/**
 * Test Organization Helper Script
 *
 * This script helps reorganize existing tests into the new directory structure.
 * Run with: php tests/organize-tests.php
 */

class TestOrganizer
{
    private array $testMappings = [
        // Feature tests mappings
        'feature' => [
            // Public facing tests
            '*EventController*' => 'Feature/Public/Events/',
            '*CategoryController*' => 'Feature/Public/Categories/',
            '*HomeController*' => 'Feature/Public/',
            '*WishlistController*' => 'Feature/Public/Wishlist/',
            '*SearchController*' => 'Feature/Public/Search/',

            // Admin tests
            '*Admin*Event*' => 'Feature/Admin/Events/',
            '*Admin*Venue*' => 'Feature/Admin/Venues/',
            '*Admin*Category*' => 'Feature/Admin/Categories/',
            '*Admin*User*' => 'Feature/Admin/Users/',
            '*Admin*Setting*' => 'Feature/Admin/Settings/',

            // Auth tests
            '*Auth*' => 'Feature/Auth/',
            '*Login*' => 'Feature/Auth/',
            '*Register*' => 'Feature/Auth/',
            '*Password*' => 'Feature/Auth/',

            // API tests
            '*Api*' => 'Feature/Api/V1/',

            // Integration tests
            '*Integration*' => 'Feature/Integration/',
            '*Consistency*' => 'Feature/Integration/',
            '*Association*' => 'Feature/Integration/',
        ],

        // Unit tests mappings
        'unit' => [
            '*Service*' => 'Unit/Services/',
            '*Action*' => 'Unit/Actions/',
            '*Model*' => 'Unit/Models/',
            '*Data*' => 'Unit/DataTransferObjects/',
            '*DTO*' => 'Unit/DataTransferObjects/',
            '*Helper*' => 'Unit/Helpers/',
            '*Rule*' => 'Unit/Rules/',
            '*Enum*' => 'Unit/Enums/',
        ]
    ];

    private string $testsPath;

    public function __construct()
    {
        $this->testsPath = __DIR__;
    }

    public function organize(): void
    {
        echo "🚀 Starting test organization...\n\n";

        $this->organizeFeatureTests();
        $this->organizeUnitTests();
        $this->generateReport();

        echo "\n✅ Test organization completed!\n";
    }

    private function organizeFeatureTests(): void
    {
        echo "📁 Organizing Feature tests...\n";

        $featureDir = $this->testsPath . '/Feature';
        $files = $this->getTestFiles($featureDir);

        foreach ($files as $file) {
            $filename = basename($file);
            $targetDir = $this->getTargetDirectory($filename, 'feature');

            if ($targetDir) {
                $this->moveTest($file, $targetDir . $filename);
            }
        }
    }

    private function organizeUnitTests(): void
    {
        echo "🔬 Organizing Unit tests...\n";

        $unitDir = $this->testsPath . '/Unit';
        $files = $this->getTestFiles($unitDir);

        foreach ($files as $file) {
            $filename = basename($file);
            $targetDir = $this->getTargetDirectory($filename, 'unit');

            if ($targetDir) {
                $this->moveTest($file, $targetDir . $filename);
            }
        }
    }

    private function getTestFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile() && $file->getExtension() === 'php' &&
                str_ends_with($file->getFilename(), 'Test.php')
            ) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function getTargetDirectory(string $filename, string $type): ?string
    {
        $mappings = $this->testMappings[$type] ?? [];

        foreach ($mappings as $pattern => $targetDir) {
            if (fnmatch($pattern, $filename, FNM_CASEFOLD)) {
                $fullPath = $this->testsPath . '/' . $targetDir;
                $this->ensureDirectoryExists($fullPath);
                return $fullPath;
            }
        }

        return null;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
            echo "  📁 Created directory: {$directory}\n";
        }
    }

    private function moveTest(string $source, string $destination): void
    {
        if ($source === $destination) {
            return;
        }

        if (file_exists($destination)) {
            echo "  ⚠️  Destination exists, skipping: " . basename($destination) . "\n";
            return;
        }

        if (rename($source, $destination)) {
            echo "  ✅ Moved: " . basename($source) . " → " . str_replace($this->testsPath . '/', '', dirname($destination)) . "/\n";
        } else {
            echo "  ❌ Failed to move: " . basename($source) . "\n";
        }
    }

    private function generateReport(): void
    {
        echo "\n📊 Test Organization Report:\n";
        echo "============================\n";

        $directories = [
            'Feature/Public/Events',
            'Feature/Public/Categories',
            'Feature/Public/Wishlist',
            'Feature/Admin/Events',
            'Feature/Admin/Venues',
            'Feature/Auth',
            'Unit/Services',
            'Unit/Models',
            'Unit/Actions',
        ];

        foreach ($directories as $dir) {
            $fullPath = $this->testsPath . '/' . $dir;
            if (is_dir($fullPath)) {
                $count = count(glob($fullPath . '/*Test.php'));
                echo sprintf("  %-30s %d tests\n", $dir, $count);
            }
        }

        echo "\n📋 Next Steps:\n";
        echo "1. Review moved tests for any broken imports\n";
        echo "2. Update namespaces if needed\n";
        echo "3. Run tests to ensure everything works: ./vendor/bin/pest --parallel\n";
        echo "4. Consider converting tests to Pest syntax for better performance\n";
    }
}

// Run the organizer
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $organizer = new TestOrganizer();
    $organizer->organize();
}
