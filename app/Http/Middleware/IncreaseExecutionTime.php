<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IncreaseExecutionTime
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read desired execution time from config (fallback to 300 seconds)
        $seconds = (int) config('performance.max_execution_time', 300);

        if ($seconds > 0) {
            // Try both ini_set and set_time_limit to maximize compatibility
            // Some hosts disable one or the other.
            if (function_exists('ini_set')) {
                @ini_set('max_execution_time', (string) $seconds);
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit($seconds);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
