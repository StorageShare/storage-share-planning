<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSyslogCommand extends Command
{
    protected $signature = 'logs:test-syslog {--count=5 : Number of test log entries to generate}';

    protected $description = 'Generate test log entries to verify syslog functionality';

    public function handle(): int
    {
        $count = (int) $this->option('count');

        $this->info("Generating {$count} test log entries...");

        $testMessages = [
            'info' => [
                'Syslog test entry: Application started successfully',
                'User authentication completed',
                'Database connection established',
                'Cache cleared successfully',
                'Background job processed',
            ],
            'warning' => [
                'Test warning: High memory usage detected',
                'Test warning: Slow query detected',
                'Test warning: External API response delayed',
            ],
            'error' => [
                'Test error: Failed to connect to external service',
                'Test error: Validation failed for user input',
            ],
            'debug' => [
                'Debug: Processing user request',
                'Debug: Cache miss for key: test_key',
                'Debug: Query executed in 150ms',
            ],
        ];

        $levels = array_keys($testMessages);

        for ($i = 1; $i <= $count; $i++) {
            $level = $levels[array_rand($levels)];
            $messages = $testMessages[$level];
            $message = $messages[array_rand($messages)]." (Entry #{$i})";

            Log::log($level, $message, [
                'test_id' => $i,
                'timestamp' => now()->toISOString(),
                'command' => 'test-syslog',
            ]);

            $this->line("✓ Generated {$level} log: {$message}");
        }

        $this->info("\n🎉 Successfully generated {$count} test log entries!");
        $this->comment('You can now view them in the Syslog Viewer: /admin/logs/syslog');
        $this->comment('Or via CLI: php artisan logs:syslog');

        return 0;
    }
}
