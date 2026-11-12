<?php

namespace App\Support;

/**
 * Builds absolute URLs to the Next.js frontend (root site or a tenant subdomain).
 */
class FrontendUrl
{
    public static function root(string $path = '/'): string
    {
        return self::build(config('frontend.root_domain'), $path);
    }

    public static function tenant(string $slug, string $path = '/'): string
    {
        return self::build($slug.'.'.config('frontend.root_domain'), $path);
    }

    private static function build(string $host, string $path): string
    {
        $port = (string) config('frontend.port');
        $portSuffix = in_array($port, ['', '80', '443'], true) ? '' : ':'.$port;

        return config('frontend.protocol').'://'.$host.$portSuffix.'/'.ltrim($path, '/');
    }
}
