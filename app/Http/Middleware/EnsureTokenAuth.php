<?php

namespace App\Http\Middleware;

use App\Services\UserTokenService;
use App\Support\CurrentUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        $token = str_starts_with($header, 'Bearer ')
            ? trim(substr($header, 7))
            : '';

        if ($token === '') {
            return $this->unauthorized();
        }

        $userToken = app(UserTokenService::class)->resolve($token);

        if ($userToken === null) {
            return $this->unauthorized();
        }

        app(UserTokenService::class)->touch($userToken, $request->ip(), $request->userAgent());

        CurrentUser::set($userToken->user);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Необходима авторизация'], 401);
    }
}