<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SSOController extends Controller
{
    /**
     * 重定向至 EDM 系統並夾帶 SSO Token JWT 版本
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToEdm(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // 準備 JWT Payload
        $payload = [
            'iss' => config('app.url'),          // 發行者
            'sub' => 'edm-sso',                 // 主題
            'uid' => $user->id,                 // 使用者 ID
            'iat' => time(),                    // 發行時間
            'exp' => time() + 60,               // 效期 60 秒
        ];

        // 使用 APP_KEY 作為簽名密鑰
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        // 生成 JWT Token (使用 HS256 演算法)
        $token = JWT::encode($payload, $key, 'HS256');

        // 從環境變數讀取 EDM 網址
        $edmUrl = config('app.edm_url', env('EDM_URL', 'https://uatedm.hwacom.com'));

        // 拼接目標網址，夾帶 Token
        $redirectUrl = rtrim($edmUrl, '/') . '?token=' . $token;

        return redirect()->away($redirectUrl);
    }

    /**
     * 驗證 SSO Token 並回傳使用者資訊與 Access Token (JWT 版本)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');

        try {
            // 使用 APP_KEY 進行解碼驗證
            $key = config('app.key');
            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            $decoded = JWT::decode($token, new \Firebase\JWT\Key($key, 'HS256'));
            $userId  = $decoded->uid;
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid or expired SSO token: ' . $e->getMessage()], 401);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }

        // 產生 Sanctum Token
        $accessToken = $user->createToken('edm-sso-token')->plainTextToken;

        // 取得使用者角色陣列
        $roles = $user->roles->pluck('name')->toArray();

        // 依照 EDM 預期格式回傳
        return response()->json([
            'code'    => 0,
            'message' => 'success',
            'data'    => [
                'accessToken' => $accessToken,
                'userInfo'    => [
                    'userId'   => $user->id,
                    'realName' => $user->name,
                    'email'    => $user->email,
                    'roles'    => $roles,
                    'homePath' => '/analytics',
                ],
            ],
        ]);
    }
}
