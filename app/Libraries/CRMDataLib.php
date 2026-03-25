<?php

namespace App\Libraries;

use App\Models\CRM\Common\CRMFiles;
use Exception;
use Google\Cloud\Core\Exception\NotFoundException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CRMDataLib
{
   // TODO:get設備詳細資訊
    public static function getDetail($device_record)
    {
        try {
            $data    = null;
            $project = $device_record->project;
            $data    = [
                'project_id'  => $project->id,
                'company_id'  => $project->company_id,
                'case_no'     => $project->case_no,
                'order_no'    => $project->order_no,
                'contract_no' => $project->contract_number,
            ];
        } catch (\Exception $e) {
        }

        return $data;
    }

    /**
     * 篩除不存在檔案
     * @param (CRMFiles&Model)|((EloquentCollection&Collection)&iterable<CRMFiles>)|null $files
     * @return array|EloquentCollection<Files>
     */
    public static function filesFilter(CRMFiles|EloquentCollection|null $files): CRMFiles|EloquentCollection|array
    {
        $disk = config('filesystems.crm_default');

        if (is_null($files)) {
            $files = [];
        } elseif ($files instanceof CRMFiles) { // 單筆檔案
            try {
                // 檔案完成時才確認檔案是否存在
                if ($files->status === 1) {
                    Storage::disk($disk)->size($files->path);
                }
            } catch (NotFoundException|\Throwable $e) {
                if (filled($files->path)) {
                    Log::error($e, [$files]);
                }
                $files = [];
            }
        } else { // 多筆檔案
            foreach ($files as $key => $file) {
                try {
                    // 檔案完成時才確認檔案是否存在
                    if ($file->status == 1) {
                        Storage::disk($disk)->size($file->path);
                    }
                } catch (NotFoundException|\Throwable $e) {
                    if (filled($file->path)) {
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
        $path              = null;
        $filesystem_driver = config('filesystems.crm_default');
        switch ($filesystem_driver) {
            case config('filesystems.crm_default'):
            case 'local':
            case 'gcs':
                $file_path = $file->path;
                $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
                if ($filesystem_driver == 'gcs') {
                    $file_path = 'public' . $file_path;
                }
                switch ($file_type) {
                    case 'jpeg':
                    case 'jpg':
                    case 'gif':
                    case 'png':
                        $path = 'data:image/' . $file_type . ';base64,' . base64_encode(Storage::disk($filesystem_driver)->get($file_path));
                        break;

                    default:
                        $path = $file_path;
                        break;
                }
                break;
        }

        return $path;
    }

    public static function QRcodeCheckFilePath($file)
    {
        $file_path = $file->path;
        $file_type = pathinfo($file_path, PATHINFO_EXTENSION);
        $path      = 'data:image/' . $file_type . ';base64,' . base64_encode(file_get_contents($file_path));

        return $path;
    }

    /**
     *  取得project合約起迄日
     * @param \App\Models\Project
     * @return Object  {
     *              string: contract_start  合約起日
     *              string: contract_end    合約迄日
     *          }
     */
    public function getProjectContract($project)
    {
        $project_custom        = $project->project_custom;
        $res                   = [];
        $res['contract_start'] = $project_custom ? $project_custom->contract_start_date : $project->contract_start_date;
        $res['contract_end']   = $project_custom ? $project_custom->contract_end_date : $project->contract_end_date;

        return $res;
    }

    /**
     *  將日期字串轉為Y-m-d H:i(w)
     * @param String  日期字串
     * @param boolean 是否要時間
     * @return String  Y-m-d H:i(w)
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
     * @param String  月份
     * @return String  季
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
     *  字數限制
     * @param string $string 字串
     * @param int $limit 字數
     * @param string $encoding 字串編碼
     * @return string
     */
    public static function StringLimit($string, $limit, $encoding = 'UTF-8')
    {
        $string_res = $string;

        if ($string && mb_strlen($string, $encoding) > $limit) { // 字數超過限制
            $string_res = mb_substr($string, 0, $limit, $encoding) . '...';
        }

        return $string_res;
    }

    /**
     *  回傳拖曳board
     * @return string $html
     */
    public static function drap_board_component()
    {
        $html = '<div class=\'py-3 px-5 inner_board\' ondrop=\'inner_drop(event, this)\' ondragenter=\'inner_dragenter(event)\' ondragover=\'inner_dragover(event, this)\' ondragleave=\'inner_dragleave(event, this)\'></div>';

        return $html;
    }

    /**
     *  檢查檔案是否存在於本地或 Google Cloud Storage
     * @param array $files 檔案陣列
     * @return array $filteredFiles 存在的檔案陣列
     */
    public static function customs_check_file($files = [])
    {
        if ($files == [] || $files == null) {
            return $files;
        }
            $filteredFiles = [];
            foreach ($files as $file) {
                try {
                    // 檢查檔案是否存在於本地
                    $localExists = Storage::disk('public_crm')->exists($file->path);
                    // 如果本地不存在，檢查 Google Cloud Storage
                    $gcsExists = !$localExists ? Storage::disk('gcs')->exists('public' . $file->path) : false;

                    if ($localExists || $gcsExists) {
                        // 如果檔案存在，附加 URL 和大小資訊
                        $file->url       = $localExists ? Storage::disk('public_crm')->url($file->path) : Storage::disk('gcs')->url('public/' . $file->path);
                        $file->size      = $localExists ? Storage::disk('public_crm')->size($file->path) : Storage::disk('gcs')->size('public/' . $file->path);
                        $filteredFiles[] = $file;
                    }
                } catch (Exception $e) {
                    // 記錄錯誤日誌
                    Log::error('檔案檢查失敗：' . $file->path . '，錯誤訊息：' . $e->getMessage());
                }
            }

            return $filteredFiles;
    }
}
