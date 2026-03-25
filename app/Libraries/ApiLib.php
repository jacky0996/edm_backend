<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class ApiLib
{
    /**
     * @param string $url
     * @param array $payload
     * @return string $result
     */
    public static function postAPI($url = '', $payload = []): string
    {
        try {
            // 初始化 cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, config('app.curl_ssl_verify', false));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, config('app.curl_ssl_verify', false));

            // Return response instead of printing.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Send request.
            $result = curl_exec($ch);
            unset($ch);

            return $result;
        } catch (\Exception $e) {
            Log::error($e);

            return 'error：' . $e;
        }
    }

    /**
     * GET Method
     *
     * @param  string  $url
     * @param  string  $contentType
     * @return string $result
     */
    public static function getAPI(string $url, $contentType = 'application/json'): string
    {
        try {
            // 初始化 cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:' . $contentType]);
            if (isset($_COOKIE['token']) && $_COOKIE['token']) {
                curl_setopt($ch, CURLOPT_COOKIE, 'token=' . $_COOKIE['token']);
            }
            if (isset($_COOKIE['OauthToken']) && $_COOKIE['OauthToken']) {
                curl_setopt($ch, CURLOPT_COOKIE, 'OauthToken=' . $_COOKIE['OauthToken']);
            }
            // Return response instead of printing.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Send request.
            $result = curl_exec($ch);
            unset($ch);

            return $result;
        } catch (\Exception $e) {
            Log::error('getAPI error：' . $e->getMessage());

            return false;
        }
    }

    /**
     *  api回傳
     *  @param  object  $data           要回傳的資料
     *  @param  boolean $success        0:失敗, 1:成功
     *  @param  string  $message        回傳訊息
     *  @param  int     $response_code  HTTP狀態碼
     *  @return object {
     *              $response_code
     *              $success
     *              $message
     *              $data
     *          }
     */
    public static function returnApiResponse($data = null, $success = true, $message = 'success', $response_code = 200)
    {
        $response = [
            'response_code' => $response_code,
            'success'       => $success,
            'message'       => $message,
            'data'          => $data,
        ];

        return $response;
    }

    // 成功回傳
    public static function successReturn($source, $id, $creator, $data = [])
    {
        return json_encode([
            'source'     => $source,
            'id'         => $id,
            'creator'    => $creator,
            'statusCode' => 1,
            'data'       => $data,
            'message'    => 'success',
        ]);
    }

    // Try Catch 錯誤回傳
    public static function catchErrorReturn($source, $creator, $errorMsg): string
    {
        return json_encode([
            'source'     => $source,
            'creator'    => $creator,
            'statusCode' => 99,
            'message'    => $errorMsg,
        ]);
    }

    // API 錯誤回傳
    public static function errorReturn($source, $creator, $code): string
    {
        $message = '';
        switch ($code) {
            case 1:
                $message = Lang::get('API/error.success');
                break;

            case 2:
                $message = Lang::get('API/error.id');
                break;

            case 3:
                $message = Lang::get('API/error.source');
                break;

            case 4:
                $message = Lang::get('API/error.apiKey');
                break;

            case 5:
                $message = Lang::get('API/error.time');
                break;

            case 6:
                $message = Lang::get('API/error.emptyList');
                break;

            case 7:
                $message = Lang::get('API/error.cancel_reject');
                break;

            case 8:
                $message = Lang::get('API/error.email');
                break;

            case 9:
                $message = Lang::get('API/error.excelLimit');
                break;

            case 90:
                $message = Lang::get('API/error.signIn_repeat');
                break;

            case 91:
                $message = Lang::get('API/error.repeat');
                break;

            case 92:
                $message = Lang::get('API/error.emptyData');
                break;

            case 99:
                $message = Lang::get('API/error.default');
                break;
        }

        return json_encode([
            'source'     => $source,
            'creator'    => $creator,
            'statusCode' => $code,
            'message'    => $message,
        ]);
    }

    //簽到回傳
    public static function successSignIn($member)
    {
        return json_encode([
            'data'       => $member,
            'statusCode' => 1,
            'message'    => 'success',
        ]);
    }

    /**
     *  紀錄mail detail
     *  @param  string  $mail           電子信箱
     *  @param  string  $message_id     電子信箱id(laravel自動產生)
     *  @return object
     */
    public static function storeEDMRecord($mail, $message_id)
    {
        $response = null;

        if (in_array(config('mail.default'), ['ses', 'ses-v2'])) { // 透過AWS發信，並記錄mail detail
        $mailID_url       = config('mail.mail_url') . '/api/sendSingleMail';
            $send_payload = [
                'creator'    => 1,
                'source'     => 'HWS',
                'from'       => config('mail.from.address'),
                'to'         => $mail,
                'subject'    => 'crm_mail',
                'content'    => 'crm_mail',
                'start_time' => date('Y-m-d H:i'),
                'end_time'   => date('Y-m-d H:i:s', strtotime('+1 hours')),
                'type'       => '3',
                'mail_id'    => '<' . $message_id . '>',
            ];

            $response = self::postAPI($mailID_url, $send_payload);
        }

        return $response;
    }

    // EDM CK Editor
    public static function edmCKContent($content)
    {
        $str = '<img style="max-width:100%"';

        // 判斷是否存在字串
        if (!strpos($content, $str)) {
            // 取代圖片
            return str_replace('<img', $str, $content);
        }

        return $content;
    }
}
