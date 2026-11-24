<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.payment.webhook_secret');
        $signature = (string) $request->header('X-Signature');

        // HMAC of the exact raw body, using the shared secret
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        // Reject if no secret is configured, or the signature doesn't match
        if ($secret === '' || !hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature.');
        }

        return $next($request);
    }
}
