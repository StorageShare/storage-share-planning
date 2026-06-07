<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Services\CsvTaskImportService;
use Illuminate\Console\Command;

class TestLocationMatchingCommand extends Command
{
    protected $signature = 'csv:test-location-matching';

    protected $description = 'Test the improved location matching functionality';

    public function handle(): int
    {
        $this->info('🔍 Testing improved location matching functionality...');

        // Get some existing locations for testing
        $existingLocations = Location::limit(5)->get();

        if ($existingLocations->isEmpty()) {
            $this->error('No locations found in database. Please add some locations first.');

            return 1;
        }

        $this->info('📍 Found '.$existingLocations->count().' existing locations:');
        foreach ($existingLocations as $location) {
            $this->line("  - {$location->name}".($location->address ? " ({$location->address})" : ''));
        }

        // Create test cases based on existing locations
        $testCases = [];

        foreach ($existingLocations as $location) {
            $originalName = $location->name;

            // Generate various test cases
            $testCases[] = [
                'input' => $originalName,
                'expected' => $location,
                'test_type' => 'exact_match',
            ];

            // Test case-insensitive
            $testCases[] = [
                'input' => strtoupper($originalName),
                'expected' => $location,
                'test_type' => 'case_insensitive',
            ];

            // Test with comma variations
            if (str_contains($originalName, ',')) {
                $testCases[] = [
                    'input' => str_replace(',', '', $originalName),
                    'expected' => $location,
                    'test_type' => 'comma_removed',
                ];
            } else {
                $parts = explode(' ', $originalName, 2);
                if (count($parts) === 2) {
                    $testCases[] = [
                        'input' => $parts[0].', '.$parts[1],
                        'expected' => $location,
                        'test_type' => 'comma_added',
                    ];
                }
            }

            // Test with punctuation variations
            $testCases[] = [
                'input' => str_replace(['.', '-', '_'], ' ', $originalName),
                'expected' => $location,
                'test_type' => 'punctuation_normalized',
            ];

            // Test with slight typos (only for first location)
            if ($location === $existingLocations->first()) {
                $testCases[] = [
                    'input' => $this->introduceTypo($originalName),
                    'expected' => $location,
                    'test_type' => 'typo_test',
                ];
            }
        }

        // Add some manual test cases for the specific example
        $testCases[] = [
            'input' => 'Leeuwarden Lange Marktstraat 11',
            'expected' => null, // Will be determined by matching
            'test_type' => 'manual_example_1',
        ];

        $testCases[] = [
            'input' => 'Leeuwarden, Lange Marktstraat 11',
            'expected' => null,
            'test_type' => 'manual_example_2',
        ];

        // Test the matching
        $this->info("\n🧪 Running ".count($testCases)." test cases...\n");

        $csvService = new CsvTaskImportService;
        $successCount = 0;
        $failureCount = 0;

        foreach ($testCases as $index => $testCase) {
            $this->line('Test '.($index + 1).": {$testCase['test_type']}");
            $this->line("  Input: '{$testCase['input']}'");

            // Use reflection to access the private method
            $reflection = new \ReflectionClass($csvService);
            $findLocationMethod = $reflection->getMethod('findLocation');
            $findLocationMethod->setAccessible(true);

            $foundLocation = $findLocationMethod->invoke($csvService, $testCase['input']);

            if ($foundLocation) {
                $this->line("  ✅ Found: {$foundLocation->name}".($foundLocation->address ? " ({$foundLocation->address})" : ''));

                if ($testCase['expected'] && $foundLocation->id === $testCase['expected']->id) {
                    $this->info('  🎯 MATCH: Correctly matched expected location');
                    $successCount++;
                } elseif (! $testCase['expected']) {
                    $this->info('  ℹ️  Manual test case - please verify if this is correct');
                    $successCount++;
                } else {
                    $this->error("  ❌ MISMATCH: Expected {$testCase['expected']->name}");
                    $failureCount++;
                }
            } else {
                $this->line('  ❌ No match found');
                if ($testCase['expected']) {
                    $this->error("  ❌ FAILED: Expected to find {$testCase['expected']->name}");
                    $failureCount++;
                } else {
                    $this->info('  ℹ️  No expected match - this is OK');
                    $successCount++;
                }
            }

            $this->line('');
        }

        // Summary
        $this->info('📊 Test Results Summary:');
        $this->info("  ✅ Successful matches: {$successCount}");
        if ($failureCount > 0) {
            $this->error("  ❌ Failed matches: {$failureCount}");
        } else {
            $this->info("  ❌ Failed matches: {$failureCount}");
        }

        $successRate = $successCount / ($successCount + $failureCount) * 100;
        $this->info('  📈 Success rate: '.round($successRate, 1).'%');

        $this->info("\n💡 You can check the logs for detailed matching information:");
        $this->comment("  php artisan logs:syslog | grep 'Location matching'");

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Introduce a small typo for testing fuzzy matching.
     */
    private function introduceTypo(string $original): string
    {
        if (strlen($original) < 5) {
            return $original;
        }

        // Replace one character in the middle
        $middle = strlen($original) / 2;
        $chars = str_split($original);

        // Replace with a similar character
        $replacements = [
            'a' => 'e', 'e' => 'a', 'i' => 'e', 'o' => 'a',
            'n' => 'm', 'm' => 'n', 'r' => 'l', 'l' => 'r',
            't' => 'd', 'd' => 't',
        ];

        $targetChar = $chars[(int) $middle];
        if (isset($replacements[strtolower($targetChar)])) {
            $chars[(int) $middle] = $replacements[strtolower($targetChar)];
        }

        return implode('', $chars);
    }
}
