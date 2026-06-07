<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = Config::get('services.external_api.secret');

        if (! $secret) {
            return response()->json([
                'message' => 'External API signature is not configured.',
            ], 500);
        }

        $signature = $request->header('X-Api-Signature');

        if (! $signature) {
            return response()->json([
                'message' => 'Invalid external API signature.',
            ], 401);
        }

        $body = $request->getContent();
        $expected = hash_hmac('sha256', $body, $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('External API signature mismatch', [
                'received_signature' => $signature,
                'expected_signature' => $expected,
                'body_length' => strlen($body),
                'body_hex_sample' => bin2hex(substr($body, 0, 100)),
            ]);

            return response()->json([
                'message' => 'Invalid external API signature.',
            ], 401);
        }

        return $next($request);
    }
}
