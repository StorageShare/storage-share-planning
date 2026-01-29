<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = Config::get('services.external_api.secret');

        if (!$secret) {
            return response()->json([
                'message' => 'External API signature is not configured.',
            ], 500);
        }

        $signature = $request->header('X-Api-Signature');

        if (!$signature) {
            return response()->json([
                'message' => 'Invalid external API signature.',
            ], 401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json([
                'message' => 'Invalid external API signature.',
            ], 401);
        }

        return $next($request);
    }
}
