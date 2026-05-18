<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HWS Core System Verification API
    |--------------------------------------------------------------------------
    | 這是驗證前端 JWT Token 的內部核心系統 API URL
    */
    'hws_verify_url' => env('HWS_VERIFY_URL', 'https://uathws.hwacom.com/api/sso/verify-token'),

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    | 只允許下列 IP 呼叫 SSO 認證中繼站。以逗號分隔。
    | 若為 '*' 代表允許所有的前端連線 (適用於 CSR 架構)。
    */
    'allowed_edm_ips' => explode(',', env('ALLOWED_EDM_IPS', '*')),

    /*
    |--------------------------------------------------------------------------
    | Allowed EDM Frontend URL
    |--------------------------------------------------------------------------
    | 用於限制 CORS 可跨站存取的白名單
    */
    'edm_frontend_url' => env('EDM_FRONTEND_URL', 'https://uatedm.hwacom.com'),

    /*
    |--------------------------------------------------------------------------
    | SSO JWT 驗證演算法 / 密鑰 / JWKS
    |--------------------------------------------------------------------------
    |
    | 與 job_digger_admin 採同樣的微服務統一驗證原則,實際驗章在 SsoJwtVerifier。
    |
    |   jwt_algorithm  : HS256(legacy 共享密鑰) 或 RS256(JWKS 公鑰驗章)
    |   jwt_secret     : HS256 才用,跟中台 DJANGO_SECRET_KEY 一致
    |   jwks_url       : RS256 才用,中台 /.well-known/jwks.json 完整 URL
    |   jwks_cache_ttl : 公鑰本地 cache 秒數,中台金鑰輪替會自動 flush
    |
    */
    'jwt_algorithm' => env('SSO_JWT_ALGORITHM', 'HS256'),
    'jwt_secret' => env('SSO_JWT_SECRET', env('APP_KEY')),
    'jwks_url' => env('SSO_JWKS_URL'),
    'jwks_cache_ttl' => env('SSO_JWKS_CACHE_TTL', 3600),
];
