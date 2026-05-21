<?php

namespace App\Services;

use App\Models\Google\GoogleOAuthToken;
use Google\Client;
use Google\Service\Forms;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google OAuth2 服務
 *
 * 管理一個系統共用的 Google 帳號授權(3-legged OAuth)。
 * sysadmin 一次性點「連結 Google」→ 拿 refresh_token 存 DB → 之後所有
 * forms.create 都用這個身份操作,form 進該 Gmail 的 Drive。
 */
class GoogleOAuthService
{
    /**
     * EDM 系統用到的 scope。
     */
    public const SCOPES = [
        Forms::FORMS_BODY,                  // 建立 / 編輯 form
        Forms::FORMS_RESPONSES_READONLY,    // 讀取 form 回應
        'https://www.googleapis.com/auth/drive.file', // 操作 EDM 建立的 Drive 檔
        'openid',
        'email',                            // 拿到授權者 email,寫回 account_email
    ];

    /**
     * 建立一個未授權的 Google\Client(只設好 client_id / secret)。
     */
    protected function newClient(): Client
    {
        $client = new Client;
        $client->setApplicationName('EDM');
        $client->setClientId(config('services.google_oauth.client_id'));
        $client->setClientSecret(config('services.google_oauth.client_secret'));
        $client->setRedirectUri(config('services.google_oauth.redirect_uri'));
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');      // 為了拿 refresh_token
        $client->setPrompt('consent');          // 強制每次都拿 refresh_token,避免 Google 省略
        $client->setIncludeGrantedScopes(true);

        return $client;
    }

    /**
     * 給 sysadmin 點的「連結 Google」入口 — 回傳 Google 同意頁的 URL。
     */
    public function getAuthUrl(?string $state = null): string
    {
        $client = $this->newClient();
        if ($state !== null) {
            $client->setState($state);
        }

        return $client->createAuthUrl();
    }

    /**
     * callback 收到 code 後,跟 Google 換 access_token + refresh_token,寫進 DB。
     *
     * @return GoogleOAuthToken 寫入後的紀錄
     *
     * @throws \RuntimeException
     */
    public function exchangeCode(string $code): GoogleOAuthToken
    {
        $client = $this->newClient();
        $tokenData = $client->fetchAccessTokenWithAuthCode($code);

        if (! empty($tokenData['error'])) {
            throw new \RuntimeException('Google OAuth code 換 token 失敗:'.($tokenData['error_description'] ?? $tokenData['error']));
        }

        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Google OAuth 沒有回傳 access_token');
        }

        // 取得授權者的 email
        $email = $this->fetchAccountEmail($client, $tokenData);

        // 系統只保留最新一筆,先清掉舊的
        GoogleOAuthToken::query()->delete();

        return GoogleOAuthToken::create([
            'provider' => 'google',
            'account_email' => $email,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => isset($tokenData['expires_in'])
                ? now()->addSeconds((int) $tokenData['expires_in'])
                : null,
            'scope' => $tokenData['scope'] ?? implode(' ', self::SCOPES),
        ]);
    }

    /**
     * 撈系統當前唯一的 token 紀錄。
     */
    public function getActiveToken(): ?GoogleOAuthToken
    {
        return GoogleOAuthToken::query()->latest('id')->first();
    }

    /**
     * 建立一個「已套上當前 token + 自動 refresh」的 Google\Client。
     *
     * 如果 access_token 過期會用 refresh_token 換新的,並把新 token 寫回 DB。
     *
     * @throws \RuntimeException 當尚未授權時
     */
    public function getAuthorizedClient(): Client
    {
        $token = $this->getActiveToken();
        if (! $token) {
            throw new \RuntimeException('Google 帳號尚未授權,請先到設定頁完成 OAuth 連結');
        }

        $client = $this->newClient();
        $tokenArray = [
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expires_at
                ? max(0, $token->expires_at->diffInSeconds(now(), false) * -1)
                : 0,
            'created' => $token->updated_at?->timestamp ?? time(),
            'scope' => $token->scope,
        ];
        $client->setAccessToken($tokenArray);

        if ($client->isAccessTokenExpired()) {
            if (empty($token->refresh_token)) {
                throw new \RuntimeException('Google 帳號授權已過期且缺少 refresh_token,請重新連結');
            }
            $refreshed = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (! empty($refreshed['error'])) {
                throw new \RuntimeException('Google refresh_token 失效,請重新連結:'.($refreshed['error_description'] ?? $refreshed['error']));
            }

            $token->update([
                'access_token' => $refreshed['access_token'] ?? $token->access_token,
                'refresh_token' => $refreshed['refresh_token'] ?? $token->refresh_token,
                'expires_at' => isset($refreshed['expires_in'])
                    ? now()->addSeconds((int) $refreshed['expires_in'])
                    : $token->expires_at,
                'scope' => $refreshed['scope'] ?? $token->scope,
            ]);
        }

        return $client;
    }

    /**
     * 解除授權:跟 Google 撤銷 token + 清 DB。
     */
    public function revoke(): bool
    {
        $token = $this->getActiveToken();
        if (! $token) {
            return true;
        }

        try {
            $client = $this->newClient();
            $client->revokeToken($token->refresh_token ?: $token->access_token);
        } catch (\Throwable $e) {
            Log::warning('Google revokeToken 失敗(仍會清掉本地 DB):'.$e->getMessage());
        }

        GoogleOAuthToken::query()->delete();

        return true;
    }

    /**
     * 拿授權者的 email — raw HTTP 打 userinfo endpoint
     * (避免依賴 google/apiclient-services 的 Oauth2 模組,該模組已被精簡掉)。
     */
    protected function fetchAccountEmail(Client $client, array $tokenData): ?string
    {
        try {
            $accessToken = $tokenData['access_token'] ?? null;
            if (! $accessToken) {
                return null;
            }
            $resp = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');
            if (! $resp->successful()) {
                Log::warning('取得 Google userinfo 失敗 HTTP '.$resp->status().': '.$resp->body());

                return null;
            }

            return $resp->json('email');
        } catch (\Throwable $e) {
            Log::warning('取得 Google userinfo 失敗:'.$e->getMessage());

            return null;
        }
    }
}
