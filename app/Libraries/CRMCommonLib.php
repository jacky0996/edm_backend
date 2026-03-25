<?php

namespace App\Libraries;

use App\Models\CRM\Common\CRMUser;
use App\Models\CRM\Common\Detect;
use App\Models\CRM\Device\Device;
use App\Models\CRM\Device\DeviceRecord;
use App\Models\CRM\Obstacle\Obstacle;
use App\Models\CRM\Relation\HasBind;
use App\Models\CRM\Relation\HasTrack;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;

class CRMCommonLib
{
    public static function PhoneMask($phone)
    {
        //加密
        // if (strlen($phone) > 50) {
        //    try {
        //     $decrypted = Crypt::decrypt($phone);
        //     } catch (\DecryptException $e) {
        //         echo $e->getMessage();
        //     }
        // }else{
        //     $decrypted = $phone;
        // }
        $decrypted = $phone;

        if (is_numeric(trim($decrypted))) {
            $str = mb_substr($decrypted, 0, -1, 'utf-8');

            return substr_replace($str, '***', 4, 3);
        }

            return $decrypted;
    }

    public static function MailMask($email)
    {
        $x = strpos($email, '@');

        if ($x == 1) {
            $z = substr_replace($email, '*', 0, 1);
        } else {
            $str = '';
            $y   = round(($x - 1) / 2);
            for ($i = 0; $i < $y; ++$i) {
                $str .= '*';
            }

            $z = substr_replace($email, $str, ($x - $y), $y);
        }

        return $z;
    }

    public static function NameMask($name)
    {
        $str = $name;
        $len = mb_strlen($str, 'utf-8');
        if ($len >= 6) {
            $str1 = mb_substr($str, 0, 2, 'utf-8');
            $str2 = mb_substr($str, $len - 2, 2, 'utf-8');
        } else {
            $str1 = mb_substr($str, 0, 1, 'utf-8');
            $str2 = mb_substr($str, $len - 1, 1, 'utf-8');
        }
        $res = $str1 . '***' . $str2;

        return $res;
    }

    public function PhoneAreaCode($phone)
    {
        $res = '';

        if (strlen($phone) == 10 && substr($phone, 0, 1) == 0) {
            $res = substr($phone, 0, 2);
        } elseif (strlen($phone) == 11) {
            $res = substr($phone, 0, strpos($phone, '-'));
        }

        return $res;
    }

    public function PhoneCode($phone)
    {
        $res = trim(strrchr($phone, '-'), '-');

        return $res;
    }

    public function EnumberToName($enumber)
    {
        $user = CRMUser::where('enumber', $enumber)->first();
        if ($user) {
            return $user->name;
        }

            return Lang::get('alert.no_data');
    }

    public function LabelRounded($count)
    {
        if ($count == 0) {
            return '';
        }

            return "<span class='label label-rounded label-danger'>{$count}</span>";
    }

    public static function StarButton($id, $type)
    {
        $res   = null;
        $uid   = Auth::id();
        $track = HasTrack::where('user_id', $uid)->where('trackable_id', $id)->where('trackable_type', $type)->first();

        if ($track) {
            $res = "<i class='fa fa-star text-star star' style='cursor: pointer' onclick='popTrack({$id},\"{$type}\")'></i>";
        } else {
            $res = "<i class='far fa-star text-star star' style='cursor: pointer' onclick='pushTrack({$id},\"{$type}\")'></i>";
        }

        return $res;
    }

    public static function StatusValid($bid)
    {
        $res   = false;
        $datas = HasBind::where('bind_id', $bid)->get();

        foreach ($datas as $data) {
            $res = true;

            switch ($data->bindable_type) {
                case 'App\Models\CRM\Obstacle': //客訴
                    $data   = Obstacle::withTrashed()->find($data->bindable_id);
                    $status = $data->status;

                    if ($status < 10) { // 結案或觀察後異常
                        return false;
                    }
                    break;

                case \App\Models\CRM\Common\Detect::class: //檢測or維修
                    $data = Detect::withTrashed()->find($data->bindable_id);

                    if ($data->detect_number) {
                        $status = $data->status;

                        if ($status != 3) { // 檢測完畢
                            return false;
                        }
                    }

                    if ($data->repair_number) {
                        $status = $data->repair_status;

                        if ($status != 3) { // 維修完畢
                            return false;
                        }
                    }
                    break;
            }
        }

        return $res;
    }

    public static function DeviceWarranty($device_id, $device = null) // 判斷設備保固狀態
    {
        /*
        輸入device_custom的id，判斷保固狀態
        返回值:
            0:預設值，無進入判斷
            1:合約過保
            2:合約保固中，原廠過保
            3:合約保固中，原廠保固中
        */
        try {
            $warranty_status = 0;

            if (!$device) {
                $device = Device::find($device_id);
            }

            $project         = $device->project;
            $project_custom  = $project->project_custom;
            $device_warranty = $device->warranty_last;
            $todate          = date('Y-m-d');
            if ($project) { // 判斷合約保固 'ERP'
                $project_warranty = $project->contract_end_date;

                if ($project_warranty) { // 若有合約保固日期
                    if (strtotime($project_warranty) >= strtotime($todate)) { // 合約保固中
                        $warranty_status = 3;
                    } else {
                        $warranty_status = 1;
                    }
                }
            }

            if ($project_custom) { // 判斷合約保固 'CUSTOM'
                $project_custom_warranty = $project_custom->contract_end_date;
                if ($project_custom_warranty) { // 若有合約保固日期
                    if (strtotime($project_custom_warranty) >= strtotime($todate)) { // 合約保固中
                        $warranty_status = 3;
                    } else {
                        $warranty_status = 1;
                    }
                }
            }
            if ($device_warranty && $warranty_status != 1 && strtotime($device_warranty) != '-62170013160') { // 判斷設備原廠保固 'CUSTOM'
                if (strtotime($device_warranty) < strtotime($todate)) { // 設備原廠過保
                    $warranty_status = 2;
                }
            }
        } catch (\Exception $e) {
        }

        return $warranty_status;
    }

    public static function ToolTip($lang, $position = 'right') // 燈泡提示
    {
        $res = "<i class='fas fa-lightbulb text-hover-warning' data-toggle='tooltip' data-html='true' data-placement='{$position}' title='{$lang}'></i>";

        return $res;
    }

    public static function HasDeviceRecord($serial_number = null, $device_id = null) // 檢查有序號設備是否報修中，決定是否可以新增DeviceRecord
    {
        $res           = false; // 能新建
        $device_record = DeviceRecord::whereNotNull('serial_number')
            ->where(function ($qry) {
                $qry->where('status', '!=', 4) // 尚未完修
                    ->where('detect_status', 1); // 要修理
                $qry->orwhere('detect_status', 2) // 不修理
                    ->where('detect_result', 7) // 原廠保固過期
                    ->where('sales_type', 0); // 尚未決定;
                $qry->orwhere('detect_status', 2) // 不修理
                    ->where('detect_result', 5) // 合約保固過期
                    ->where('sales_type', 0); // 尚未決定;
            })
            ->where(function ($qry) {
                $qry->whereHas('obstacle')
                    ->orwhereHas('detect');
            })
            ->whereHas('device', function ($qry) {
                $qry->whereNotNull('serial_number'); // 不是被強制更換的device_record
            });

        if ($serial_number) {
            $device_record = $device_record->where('serial_number', $serial_number);
        }

        if ($device_id) {
            $device_record = $device_record->where('device_id', $device_id);
        }

        $device_record = $device_record->orderBy('id', 'desc')->first(); // 抓最新一筆紀錄

        if ($device_record) {
            $obstacle = $device_record->obstacle;
            $detect   = $device_record->detect;

            if ($obstacle && !$detect) { // 判斷客訴單狀態
                $status = $obstacle->status;

                if ($status >= 6) { // 待工程結案
                    $res = false; // 能新建
                } else {
                    $res = $obstacle->obstacle_number;
                }
            } elseif ($detect) { // 判斷維修單狀態
                $status = $detect->repair_status;

                if ($status >= 3) { // 已結案
                    $res = false; // 能新建
                } else {
                    $res = $detect->repair_number ?? $detect->detect_number;
                }
            }
        } else {
            $res = false; // 能新建
        }

        return $res;
    }

    public static function check_clockin_time_out($calendar, $date, $clockin_type) // 確認 出發 > 到場 > 離場 時間有無過早
    { // 輸入分別為：行事曆，打卡時間，打卡類型
        $res     = true; // 打卡時間無誤，可直接打卡
        $clockin = $calendar->clockin;

        switch ($clockin_type) { // 判斷打卡種類
            case '4': // 到場->與出發做比較
                $org_clockin_at = $clockin->where('type', 3)->first();
                break;

            case '5': // 離場->與到場做比較
                $org_clockin_at = $clockin->where('type', 4)->first();
                break;

            default:
                $org_clockin_at = false;
                break;
        }

        if ($org_clockin_at) { // 若有前次的打卡則判斷時間，不能比前次打卡
            $org_clockin_at = $org_clockin_at->clockin_at;

            if (strtotime($org_clockin_at) > strtotime($date)) {
                $res = false; // 無法打卡，時間過早
            }
        }

        return $res;
    }

    public static function ContactLink($data, $type)
    {
        $tooltip  = Lang::get('tooltip/contact.contact_link');
        $text     = Lang::get('CRM/contact.no_binding');
        $res      = "<span style='font-size: 10pt;' class='text-muted' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link mr-1'></i>{$text}</span>";
        $contacts = $data->contact_record;

        if ($contacts) {
            $total = $contacts->count();

            if ($total == 1) {
                $contact = $contacts->first();
                $res     = "<a style='font-size: 10pt;' class='font-weight-bolder' href='/contact_records/view/{$contact->id}' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link text-primary mr-1'></i>{$contact->contact_number}</a>";
            } elseif ($total > 1) {
                switch ($type) {
                    case 'obstacle':
                        $binding_number = $data->obstacle_number;
                        break;

                    case 'detect':
                        $binding_number = $data->detect_number;
                        break;

                    case 'repair':
                        $binding_number = $data->repair_number;
                        break;
                }
                $text = Lang::get('CRM/contact.many_binding');
                $res  = "<a style='font-size: 10pt;' class='font-weight-bolder' href='/contact_records/list' id='many_contact_record' link='{$binding_number}' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link text-primary mr-1'></i>{$text}</a>";
            }
        }

        return $res;
    }

    public static function CreateLabel($word, $color)
    {
        return "<span class='label label-lg font-weight-bolder label-light-{$color} label-inline mx-1'>{$word}</span>";
    }

    // Mail 格式驗證
    public static function verityMail($mail): bool
    {
        if (preg_match('/^[-A-Za-z0-9_]+[-A-Za-z0-9_.]*[@]{1}[-A-Za-z0-9_]+[-A-Za-z0-9_.]*[.]{1}[A-Za-z]{2,5}$/', $mail)) {
            return true;
        }

            return false;
    }

    // Datatable開關UI
    public function SwitchButton($id, $status): string
    {
        $check = $status ? 'checked' : '';
        $res   = "<div class='switch'>
                        <span class='switch switch-outline switch-icon switch-success'>
                            <label>
                                <input id='switch_{$id}' type='checkbox' {$check}>
                                <span></span>
                                </label>
                            </span>
                    </div>";

        return $res;
    }

    //時間差異Ago
    public static function time_diff($created_at)
    {
        $time = time() - strtotime($created_at);
        if ($time / 604800 >= 1) {
            $ago = $created_at->format('Y-m-d H:i');
        } elseif ($time / 86400 >= 1) {
            $ago = floor($time / 86400) . \Illuminate\Support\Facades\Lang::get('common/notify.days_ago');
        } elseif ($time / 3600 >= 1) {
            $ago = floor($time / 3600) . Lang::get('common/notify.hour_ago');
        } else {
            $ago = floor($time / 60) . Lang::get('common/notify.min_ago');
        }

        return $ago;
    }

    /**
     * 取得需求標籤
     * @param object $model 有跟 Issues 關聯的 Model
     * @param string $document_number Model 的單號 (進階搜尋使用)
     * @return  string html
     */
    public static function getIssueLabel($model, $model_type, $relate_number, $class = 'h6 mx-1')
    {
        $issues = $model->issues;
        $count  = $issues->count();
        $res    = "<div class='font-weight-bolder {$class}'>";

        if ($count) { // xxx個需求＋
            $text = Lang::get('common/issue.count_issues');
            $res .= "<a href='#' onclick='openIssueList(this)' relate_number={$relate_number}>{$count} {$text}</a>
                    <i style='cursor: pointer;' class='fas fa-plus text-hover-primary' onclick='openIssueCreateModal(this)' model_id='{$model->id}' model_type='{$model_type}'></i>";
        } else { // 建立需求
            $text = Lang::get('common/issue.create');
            $res .= "<a href='#' onclick='openIssueCreateModal(this)' model_id='{$model->id}' model_type='{$model_type}'>{$text}</a>";
        }

        $res .= '</div>';

        return $res;
    }

    /**
     * 字串長度限制
     * @method WordLength
     * @param string $str 字串
     * @param int $limmit 限制字元長度
     * @return string
     */
    public static function WordLength($str, $limmit)
    {
        $total_length = mb_strlen($str);
        $str          = mb_substr($str, 0, $limmit);

        if ($total_length >= $limmit) {
            $str .= '...';
        }

        return $str;
    }

    /**
     * Vip客訴連結障礙申告
     *
     * @param $data
     * @param $type
     * @return string
     */
    public static function ContactLinkVip($data, $type)
    {
        $tooltip  = Lang::get('tooltip/contact.contact_link');
        $text     = Lang::get('CRM/contact.no_binding');
        $res      = "<span style='font-size: 10pt;' class='text-muted' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link mr-1'></i>{$text}</span>";
        $contacts = $data->contact_record;

        if ($contacts) {
            $total = $contacts->count();

            if ($total == 1) {
                $contact = $contacts->first();
                $id      = Crypt::encryptString($contact->id);
                $res     = "<a style='font-size: 10pt;' class='font-weight-bolder' href='/contact/view/{$id}' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link text-primary mr-1'></i>{$contact->contact_number}</a>";
            } elseif ($total > 1) {
                switch ($type) {
                    case 'obstacle':
                        $binding_number = $data->obstacle_number;
                        break;

                    case 'detect':
                        $binding_number = $data->detect_number;
                        break;

                    case 'repair':
                        $binding_number = $data->repair_number;
                        break;
                }
                $text = Lang::get('CRM/contact.many_binding');
                $res  = "<a style='font-size: 10pt;' class='font-weight-bolder' href='/contact/list' id='many_contact_record' link='{$binding_number}' data-toggle='tooltip' title='{$tooltip}'><i class='fas fa-link text-primary mr-1'></i>{$text}</a>";
            }
        }

        return $res;
    }
}
