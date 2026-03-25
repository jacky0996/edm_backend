<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class LogLib
{
    public static function ErrorLog($function = null, $message = null, $error = null)
    {
        //$.ajax({
        //            type : 'post',
        //            url : base_path+'/error_log',
        //            data : {
        //                'path'      : settings.ajax.url,
        //                'message'   : msg,
        //                'error'     : error_msg
        //            },
        //            dataType: "json",
        //            success : function(data){
        //                swal.fire({
        //                    position:           "center",
        //                    icon:               "error",
        //                    title:              data.res.error_code,
        //                    text:               data.res.message,
        //                    showConfirmButton:  false,
        //                    //timer:              1500
        //                });
        //            },
        //        })
        $error_code = 'E' . date('YmdHis');
        $uid        = Auth::id();

        //錯誤代碼/使用者ID/錯誤route/錯誤訊息
        Log::info($error_code . '/UID:' . $uid . '/' . $function . ' - ' . $message, $error);

        $data = [
            'error_code' => Lang::get('alert.error_code') . '：' . $error_code,
            'message'    => Lang::get('alert.record_error'),
            'error'      => $error,
        ];

        return $data;
    }
}
