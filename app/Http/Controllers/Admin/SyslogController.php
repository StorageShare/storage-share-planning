<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class SyslogController extends Controller
{
    public function index(Request $request): View
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }

        $lines = $request->get('lines', 100);
        $search = $request->get('search', '');

        $logs = $this->getSyslogEntries($lines, $search);

        return view($this->viewName('admin.logs.syslog'), compact('logs', 'lines', 'search'));
    }

    public function api(Request $request): JsonResponse
    {
        // Check if user is admin
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
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

    /**
     * @return array<int, string>
     */
    private function getSyslogEntries(int $lines = 100, string $search = ''): array
    {
        $syslogPaths = [
            '/var/log/syslog',
            '/var/log/messages',
            '/var/log/system.log'
        ];

        /** @var array<int, string> $logs */
        $logs = [];
        $appName = config('app.name', 'Laravel');
        $appEnv = config('app.env', 'production');
        $identifier = $appName . '-' . $appEnv;

        foreach ($syslogPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                try {
                    $command = "grep -i '" . addcslashes($identifier, "'\\") . "' {$path}";

                    // Add search filter if provided
                    if (!empty($search)) {
                        $command .= " | grep -i " . escapeshellarg($search);
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
