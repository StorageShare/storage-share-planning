<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyslogController extends Controller
{
    public function index(Request $request)
    {
        // Check if user is admin
        if (!Auth::check() || Auth::user()->role !== Role::ADMIN) {
            abort(403);
        }
        
        $lines = $request->get('lines', 100);
        $search = $request->get('search', '');
        
        $logs = $this->getSyslogEntries($lines, $search);
        
        return view('admin.logs.syslog', compact('logs', 'lines', 'search'));
    }
    
    public function api(Request $request)
    {
        // Check if user is admin
        if (!Auth::check() || Auth::user()->role !== Role::ADMIN) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $lines = $request->get('lines', 50);
        $search = $request->get('search', '');
        
        $logs = $this->getSyslogEntries($lines, $search);
        
        return response()->json([
            'logs' => $logs,
            'count' => count($logs),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    private function getSyslogEntries($lines = 100, $search = '')
    {
        $syslogPaths = [
            '/var/log/syslog',
            '/var/log/messages',
            '/var/log/system.log'
        ];
        
        $logs = [];
        $appName = config('app.name', 'Laravel');
        $appEnv = config('app.env', 'production');
        $identifier = $appName . '-' . $appEnv;
        
        foreach ($syslogPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                try {
                    $command = "grep -i '{$identifier}' {$path}";
                    
                    // Add search filter if provided
                    if (!empty($search)) {
                        $command .= " | grep -i '" . escapeshellarg($search) . "'";
                    }
                    
                    $command .= " | tail -{$lines}";
                    
                    $output = shell_exec($command);
                    
                    if ($output) {
                        $logLines = explode("\n", trim($output));
                        $logs = array_merge($logs, array_filter($logLines));
                    }
                    break;
                } catch (\Exception $e) {
                    // Continue to next path if this one fails
                    continue;
                }
            }
        }
        
        // Reverse to show newest first
        return array_reverse($logs);
    }
} 