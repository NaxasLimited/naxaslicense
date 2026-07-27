<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSecureActivationTransport
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isSecure()) {
            return $next($request);
        }

        $localEnvironment = app()->environment(['local', 'testing']);
        $localHost = in_array($request->getHost(), config('license.local_http_hosts'), true);

        abort_unless(config('license.allow_local_http') && $localEnvironment && $localHost, 403, 'HTTPS is required.');

        return $next($request);
    }
}
