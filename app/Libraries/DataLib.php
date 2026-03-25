<?php

namespace App\Libraries;

use App\Models\Files;
use Google\Cloud\Core\Exception\NotFoundException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataLib
{
    /**
     * 篩除不存在檔案
     * @param (Files&Model)|((EloquentCollection&Collection)&iterable<Files>)|null $files
     * @return array|EloquentCollection<Files>
     */
    public static function filesFilter(Files|EloquentCollection|null $files): Files|EloquentCollection|array
    {
        if (is_null($files)) {
            $files = [];
        } elseif ($files instanceof Files) { // 單筆檔案
            try {
                // 檔案完成時才確認檔案是否存在
                if ($files->status === 1) {
                    Storage::size('public/' . $files->path);
                }
            } catch (NotFoundException|\Throwable $e) {
                if (filled($files->path) && ($files->src_env === app()->environment())) {
                    Log::error($e, [$files]);
                }
                $files = [];
            }
        } else { // 多筆檔案
            foreach ($files as $key => $file) {
                try {
                    // 檔案完成時才確認檔案是否存在
                    if ($file->status == 1) {
                        Storage::size('public/' . $file->path);
                    }
                } catch (NotFoundException|\Throwable $e) {
                    if (filled($file->path) && ($file->src_env === app()->environment())) {
                        Log::error($e, [$file]);
                    }
                    $files->pull($key);
                }
            }

            if ($files) {
                $files = $files->values();
            }
        }

        return $files;
    }

    public static function CheckFilePath($file)
    {
        $path = null;
        // $filesystem_driver = config('filesystems.default');
        $filesystem_driver = 'sftp';

        // 获取文件的完整路径和类型
        $file_path = $file->path;
        $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
        // 检查文件是否存在
        if (!Storage::exists('public' . $file_path)) {
            return; // 或者返回错误信息或其他适当的响应
        }

        return 123;
        // 根據不同的 storage driver 處理預覽路徑
        switch ($filesystem_driver) {
            case 'local':
            case 'gcs':
            case 'sftp':
                // 圖片檔：直接讀檔轉成 base64 data URI，讓前端可以預覽縮圖
                if (in_array($file_type, ['jpeg', 'jpg', 'gif', 'png'])) {
                    $path = 'data:image/' . $file_type . ';base64,' . base64_encode(Storage::get('public' . $file_path));
                } else {
                    // 非圖片檔：僅回傳相對路徑，交由前端 fileinput 的 downloadUrl 處理
                    $path = $file_path;
                }
                break;

                // 可以添加其他存储驱动的情况
        }

        return $path;
    }

    /**
     *  將日期字串轉為Y-m-d H:i(w)
     *  @param  String  日期字串
     *  @param  boolean 是否要時間
     *  @return String  Y-m-d H:i(w)
     */
    public static function StringToDateWeek($string, $time = false)
    {
        $res       = '';
        $weekarray = ['日', '一', '二', '三', '四', '五', '六'];

        if ($string) {
            if ($time) {
                $res = date('Y-m-d H:i', strtotime($string)) . ' (' . $weekarray[date('w', strtotime($string))] . ')';
            } else {
                $res = date('m月 d日', strtotime($string)) . ' (星期' . $weekarray[date('w', strtotime($string))] . ')';
            }
        }

        return $res;
    }

    /**
     *  將月份轉為季
     *  @param  String  月份
     *  @return String  季
     */
    public static function MonthToSeason($month)
    {
        $res = 0;
        $Q1  = ['01', '02', '03'];
        $Q2  = ['04', '05', '06'];
        $Q3  = ['07', '08', '09'];
        $Q4  = ['10', '11', '12'];

        if (in_array($month, $Q1)) {
            return 1;
        }

        if (in_array($month, $Q2)) {
            return 2;
        }

        if (in_array($month, $Q3)) {
            return 3;
        }

        if (in_array($month, $Q4)) {
            return 4;
        }

        return $res;
    }

    /**
     *  回傳拖曳board
     *  @return string $html
     */
    public static function drap_board_component()
    {
        $html = '<div class=\'py-3 px-5 inner_board\' ondrop=\'inner_drop(event, this)\' ondragenter=\'inner_dragenter(event)\' ondragover=\'inner_dragover(event, this)\' ondragleave=\'inner_dragleave(event, this)\'></div>';

        return $html;
    }

    /**
     *  字數限制
     *  @param  string  $string  字串
     *  @param  int     $length  長度限制
     *  @param  boolean $br      true:換行, false:不換行 + ...
     *  @return string
     */
    public static function StringLimit($str, $length, $br = true)
    {
        // $line 记录当前行的长度 // $len utf-8字符串的长度
        $nstr = '';
        $lstr = null;
        //不換行後面加...
        if (!($br)) {
            $str  = mb_substr($str, 0, $length, 'utf-8');
            $lstr = '...';
        }
        //換行
        for ($line = 0, $len = mb_strlen($str, 'utf-8'), $i = 0; $i < $len; ++$i) {
            $v    = mb_substr($str, $i, 1, 'utf-8'); // 获取当前的汉字或字母
            $vlen = strlen($v) > 1 ? 2 : 1; // 根据二进制长度 判断出当前是中文还是英文
            if ($line + $vlen > $length && $br) { // 检测如果加上当前字符是否会超出行的最大字数
                $nstr .= '<br>'; // 超出就加上换行符
                $line = 0; // 因为加了换行符 就是新的一行 所以当前行长度设置为0
            }
            $nstr .= $v; // 加上当前字符
            $line += $vlen; // 加上当前字符的长度
        }
        $nstr .= $lstr;

        return $nstr;
    }

    /**
     * 字數限制，支援換行符處理並在指定長度插入換行，保留原換行符且避免因增加換行導致的空行
     * @param string $str 字串
     * @param int $length 長度限制
     * @param boolean $br true:換行, false:不換行 + ...
     * @return string
     */
    public static function wrapTextWithLengthLimit($str, $length, $br = true)
    {
        if (!$br) {
            return mb_substr($str, 0, $length, 'utf-8') . '...';
        }

        $nstr          = ''; // 最終返回的字串
        $segment       = ''; // 從上一個換行符號到當前位置的字串
        $len           = mb_strlen($str, 'utf-8');
        $segmentLength = 0; // 計算段落的長度，不包括換行符號

        for ($i = 0; $i < $len; ++$i) {
            $char = mb_substr($str, $i, 1, 'utf-8');

            // 如果遇到換行符號
            if ($char === "\n") {
                $nstr .= $segment . $char;
                $segment       = '';
                $segmentLength = 0; // 重置段落的長度
                continue;
            }

            $segment .= $char;
            $segmentLength += (strlen($char) > 1) ? 2 : 1; // 中文字元數算2

            // 當段落的長度超過限制或到字串最後時
            if ($segmentLength > $length || $i === $len - 1) {
                // 在不超过长度限制的最后位置插入换行符，除非已经是段落或字符串的末尾
                if ($segmentLength > $length) {
                    // 找到不超過長度限制的最後一個字串位置
                    for ($j = $length; $j < $segmentLength; $j += (strlen(mb_substr($segment, $j, 1, 'utf-8')) > 1) ? 2 : 1) {
                        // 重新計算段落最後的位置 控制$j
                    }
                    $part = mb_substr($segment, 0, $j, 'utf-8');
                    $nstr .= $part . "\n"; // 插入分段和换行符
                    $segment       = mb_substr($segment, $j, null, 'utf-8');
                    $segmentLength = mb_strlen($segment, 'utf-8'); // 重置段落長度
                } else {
                    $nstr .= $segment;
                    $segment       = '';
                    $segmentLength = 0;
                }
            }
        }

        return $nstr;
    }
}
