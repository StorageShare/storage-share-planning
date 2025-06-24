<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReadSyslogCommand extends Command
{
    protected $signature = 'logs:syslog {--lines=50 : Number of lines to show} {--follow : Follow the log in real-time}';
    
    protected $description = 'Read Laravel syslog entries';

    public function handle()
    {
        $lines = $this->option('lines');
        $follow = $this->option('follow');
        
        // Try different syslog locations
        $syslogPaths = [
            '/var/log/syslog',
            '/var/log/messages',
            '/var/log/system.log'
        ];
        
        $syslogPath = null;
        foreach ($syslogPaths as $path) {
            if (file_exists($path)) {
                $syslogPath = $path;
                break;
            }
        }
        
        if (!$syslogPath) {
            $this->error('Syslog file not found. You may need root access.');
            return 1;
        }
        
        $appName = config('app.name', 'Laravel');
        $appEnv = config('app.env', 'production');
        $identifier = $appName . '-' . $appEnv;
        
        if ($follow) {
            $this->info("Following syslog for {$identifier}... (Press Ctrl+C to stop)");
            $command = "tail -f {$syslogPath} | grep -i '{$identifier}'";
        } else {
            $this->info("Last {$lines} syslog entries for {$identifier}:");
            $command = "grep -i '{$identifier}' {$syslogPath} | tail -{$lines}";
        }
        
        try {
            passthru($command);
        } catch (\Exception $e) {
            $this->error('Error reading syslog: ' . $e->getMessage());
            $this->comment('You may need to run this command with sudo privileges.');
        }
        
        return 0;
    }
} 