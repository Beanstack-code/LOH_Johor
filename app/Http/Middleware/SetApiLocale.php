<?php

namespace App\Http\Middleware;

use App\Support\ApiLocale;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolve the API locale from the incoming request and store it in ApiLocale.
 *
 * Detection priority (first non-empty value wins):
 *   1. X-App-Locale header  (e.g. "ms")
 *   2. Accept-Language header — first language tag  (e.g. "zh,en-US" → "zh")
 *   3. locale query parameter  (e.g. ?locale=bm)
 *   4. default: "en"
 *
 * Accepted values: en, ms / bm, zh (and regional variants).
 * "bm" and regional tags are normalized inside ApiLocale::set().
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $raw = $request->header('X-App-Locale')
            ?? $this->parseAcceptLanguage($request->header('Accept-Language', ''))
            ?? $request->query('locale', 'en');

        ApiLocale::set((string) $raw);

        return $next($request);
    }

    private function parseAcceptLanguage(string $header): ?string
    {
        if (trim($header) === '') {
            return null;
        }

        // "zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7"  →  "zh-CN"
        $first = trim(explode(',', $header)[0]);
        $tag = trim(explode(';', $first)[0]);

        return $tag !== '' ? $tag : null;
    }
}
