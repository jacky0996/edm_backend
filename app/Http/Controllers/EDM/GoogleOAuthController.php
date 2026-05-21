<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Services\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Google OAuth 連結管理(system-wide single identity)
 *
 * sysadmin 一次性流程:start → Google 同意頁 → callback → 寫 DB → 跳回前端落地頁。
 * 之後 EDM 所有 forms.create 都用這個身份。
 */
class GoogleOAuthController extends Controller
{
    protected GoogleOAuthService $oauth;

    public function __construct(GoogleOAuthService $oauth)
    {
        $this->oauth = $oauth;
    }

    /**
     * 取得 Google 同意頁 URL。前端 GET 此 endpoint 後重導到回傳的 url。
     */
    public function start(Request $request): JsonResponse
    {
        if (! config('services.google_oauth.client_id') || ! config('services.google_oauth.client_secret')) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => 'Google OAuth 尚未設定:請先於 .env 設定 GOOGLE_OAUTH_CLIENT_ID / GOOGLE_OAUTH_CLIENT_SECRET',
            ], 500);
        }

        try {
            $url = $this->oauth->getAuthUrl();

            return response()->json([
                'code' => 0,
                'status' => true,
                'data' => ['auth_url' => $url],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '無法產生 Google 授權連結:'.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Google 跳回來的 callback(GET)。
     *
     * 注意:此 endpoint 不掛 JWT middleware,因為 Google 用瀏覽器導頁過來,沒有 EDM 的 token。
     */
    public function callback(Request $request): RedirectResponse
    {
        $landing = config('services.google_oauth.frontend_landing');

        $error = $request->query('error');
        if ($error) {
            return redirect()->away($landing.'?google_oauth_status=error&reason='.urlencode($error));
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->away($landing.'?google_oauth_status=error&reason=missing_code');
        }

        try {
            $token = $this->oauth->exchangeCode($code);
            Log::info('Google OAuth 連結成功', ['email' => $token->account_email]);

            return redirect()->away($landing.'?google_oauth_status=ok&email='.urlencode($token->account_email ?? ''));
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback 失敗:'.$e->getMessage());

            return redirect()->away($landing.'?google_oauth_status=error&reason='.urlencode($e->getMessage()));
        }
    }

    /**
     * 回傳目前授權狀態與綁定的 Google 帳號。
     */
    public function status(Request $request): JsonResponse
    {
        $token = $this->oauth->getActiveToken();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => [
                'authorized' => $token !== null,
                'account_email' => $token?->account_email,
                'connected_at' => $token?->created_at?->toIso8601String(),
                'expires_at' => $token?->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 解除 Google 帳號授權:跟 Google 撤銷 + 清 DB。
     */
    public function revoke(Request $request): JsonResponse
    {
        $this->oauth->revoke();

        return response()->json([
            'code' => 0,
            'status' => true,
            'message' => '已解除 Google 帳號授權',
        ]);
    }
}
