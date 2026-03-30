<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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
            // =========================================================================
            // 步驟 1. 嚴格遵守 Zero Hardcode：從 config 取得核心系統 API
            // =========================================================================
            $hwsVerifyUrl = config('sso.hws_verify_url');
            
            // 中繼代理解耦：送出請求驗證 Token
            $response = Http::timeout(5)->post($hwsVerifyUrl, [
                'token' => $token
            ]);

            // 檢查 HWS 是否有發生 HTTP Error (如 400, 500)
            if (!$response->successful()) {
                return response()->json(['message' => 'HWS Verification API Failed'], 401);
            }

            $hwsData = $response->json();

            // =========================================================================
            // 步驟 2. 判斷 HWS 回傳的業務邏輯狀態碼 (請依照 HWS API 文件修改鍵名)
            // =========================================================================
            // 這裡假設 HWS 會回傳 {"success": true, "data": {...}}
            if (!isset($hwsData['success']) || $hwsData['success'] !== true) {
                return response()->json([
                    'message' => 'Invalid or expired SSO token from HWS', 
                    'details' => $hwsData
                ], 401);
            }

            // =========================================================================
            // 步驟 3. 資料清洗 (Data Sanitization)
            // 從核心系統回傳的複雜結構中剔除敏感資訊(薪資、權限)，僅保留 EDM 需要的核心職責欄位
            // =========================================================================
            $safeData = [
                'emp_id'     => $hwsData['data']['uid'] ?? null,
                'email'      => $hwsData['data']['email'] ?? '',
                'name'       => $hwsData['data']['name'] ?? 'HWS User',
                'department' => $hwsData['data']['department'] ?? '未指派部門',
            ];

            // 確保必填識別碼存在
            if (empty($safeData['emp_id'])) {
                return response()->json(['message' => '核心系統回傳資訊異常，缺少員工識別碼'], 401);
            }
            
            $hwsUserId = $safeData['emp_id'];

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error communicating with HWS: ' . $e->getMessage()], 500);
        }

        // =========================================================================
        // 步驟 4. 根據清洗過的安全資料 ($safeData)，在 EDM 本地資料庫找人
        // =========================================================================
        $user = User::find($hwsUserId); 
        // 提示: 或使用 User::where('email', $hwsUserEmail)->first() 找人

        if (!$user) {
            // 如果 EDM 本地還沒有這個人，要嘛拒絕登入，要嘛幫他自動建檔：
            /*
            $user = User::create([
                'id' => $hwsUserId, // 若 HWS UID 想要同步到本地 ID
                'name' => $hwsUserName,
                'email' => $hwsUserEmail,
            ]);
            */
            return response()->json(['message' => 'User verified in HWS, but not found in local EDM DB'], 401);
        }

        // =========================================================================
        // 步驟 5. HWS 驗證身份無誤，產生 EDM 本地專屬的 Sanctum Access Token
        // =========================================================================
        $accessToken = $user->createToken('edm-sso-token')->plainTextToken;

        $roles = $user->roles->pluck('name')->toArray();

        // 依照 EDM 前端架構預期的格式回傳給前端
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
