<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * 中台 SSO JWT 驗章服務 — 微服務統一驗證入口。
 *
 * 兩種模式擇一(由 config/sso.php `jwt_algorithm` 控制):
 *
 *   - HS256(legacy):與中台共用 SECRET_KEY,本地直接驗章
 *     優點:設定簡單;缺點:密鑰需在每個服務散布,洩漏面積大
 *
 *   - RS256(推薦):中台簽,各服務只持公鑰
 *     公鑰從中台 /.well-known/jwks.json 拉,本地 cache 1 小時
 *     金鑰輪替時驗章會 throw,捕捉後 flushCache + 重抓 + 重試一次
 *
 * 服務本地驗章意味著「中台短暫掛掉,業務系統仍能驗 token 通過」—
 * 這是高可用設計的核心。
 */
class SsoJwtVerifier
{
    private const JWKS_CACHE_KEY = 'sso_jwks_keys';

    public function decode(string $token): object
    {
        $algorithm = config('sso.jwt_algorithm', 'HS256');

        if (str_starts_with($algorithm, 'RS') || str_starts_with($algorithm, 'ES')) {
            return $this->decodeWithJwks($token);
        }

        // HS256 等對稱演算法:本地共用密鑰
        $secret = config('sso.jwt_secret');
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7));
        }

        return JWT::decode($token, new Key($secret, $algorithm));
    }

    /**
     * 強制清掉 JWKS cache。中台輪替金鑰、或驗章失敗時呼叫。
     */
    public function flushKeyCache(): void
    {
        Cache::forget(self::JWKS_CACHE_KEY);
    }

    /**
     * RS256/ES256 驗章:JWKS lookup + 金鑰輪替 fallback。
     */
    private function decodeWithJwks(string $token): object
    {
        try {
            return JWT::decode($token, $this->loadJwksKeys());
        } catch (UnexpectedValueException $e) {
            // 訊息含 "kid" 通常代表中台輪替了金鑰、cache 還舊的。重抓再試一次。
            if (str_contains($e->getMessage(), 'kid')) {
                $this->flushKeyCache();

                return JWT::decode($token, $this->loadJwksKeys());
            }
            throw $e;
        }
    }

    /**
     * @return array<string, Key>
     */
    private function loadJwksKeys(): array
    {
        $ttl = (int) config('sso.jwks_cache_ttl', 3600);

        // Cache 的是 JWKS 原始 JSON(可序列化),每次再 parse 成 Key 物件。
        // 不能直接 cache parseKeySet 結果 — Key 內含 OpenSSLAsymmetricKey,
        // PHP 8 起此型別禁止 serialize,file/database cache driver 都會炸。
        $jwks = Cache::remember(self::JWKS_CACHE_KEY, $ttl, function () {
            $url = config('sso.jwks_url');
            if (! $url) {
                throw new Exception('sso.jwks_url is not configured');
            }

            $response = Http::timeout(5)->get($url);
            if (! $response->ok()) {
                throw new Exception(
                    "Failed to fetch JWKS from {$url}: HTTP {$response->status()}"
                );
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }
}
