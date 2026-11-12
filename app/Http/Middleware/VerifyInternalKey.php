<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the Next.js server may call this API. Unknown callers get a 404 so the API looks absent.
 */
class VerifyInternalKey
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $internalKey = (string) config('frontend.internal_key');
        if ($internalKey === '' || ! hash_equals($internalKey, (string) $request->header('X-Internal-Key'))) {
            return response()->json(['message' => 'Not foundsss.'], 404);
        }

        return $next($request);
    }
}
