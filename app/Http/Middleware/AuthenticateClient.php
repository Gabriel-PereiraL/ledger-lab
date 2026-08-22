<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Api-Key');
        $client = is_string($token) ? ApiClient::query()->where('token_hash', hash('sha256', $token))->first() : null;
        abort_unless($client !== null, 401, 'Invalid API credentials.');
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
