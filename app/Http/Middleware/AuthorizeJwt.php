<?php

namespace App\Http\Middleware;

use App\Services\SsoJwtVerifier;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API 模式 JWT 驗證 middleware。
 *
 * 驗章工作委派給 SsoJwtVerifier — 同時支援 HS256 (legacy) 與 RS256/JWKS。
 * 跟 job_digger_admin 的 AuthorizeJwtSso 共用同一驗證原則,差別只是:
 *   - 本 middleware 是 API 模式:驗失敗回 JSON 401,不做 SSO redirect
 *   - AuthorizeJwtSso 是 Web 模式:驗失敗 302 跳中台
 */
class AuthorizeJwt
{
    public function __construct(private SsoJwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            Log::debug('JWT Middleware: Token missing or format wrong in header');

            return response()->json(['message' => 'Authorization Token not found'], 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = $this->verifier->decode($token);
            $request->merge(['auth' => (array) $decoded]);

            return $next($request);
        } catch (Exception $e) {
            Log::error('JWT Middleware FAILURE: '.$e->getMessage(), [
                'token_sample' => substr($token, 0, 15).'...',
                'algorithm' => config('sso.jwt_algorithm'),
            ]);

            return response()->json([
                'message' => 'Unauthorized: '.$e->getMessage(),
                'error_type' => get_class($e),
            ], 401);
        }
    }
}
