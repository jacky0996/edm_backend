<?php

namespace App\Libraries;

use App\Models\CRM\Common\Detect;
use App\Models\CRM\Device\Device;
use App\Models\CRM\Device\DeviceRecord;
use App\Models\CRM\Obstacle\Obstacle;
use App\Models\CRM\Relation\HasBind;
use App\Models\Dept;
use App\Models\HasTrack;
use App\Models\User;
use App\Repositories\Project\ProjectHeaderRepository;
use Cache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Psr\SimpleCache\InvalidArgumentException;

class CommonLib
{
    /**
     *
     * @param Collection $all_dates ($candidate_dates)
     * @param null|string|DateTimeInterface $critical
     * @param bool $cast_as_carbon
     * @return mixed|Carbon
     *
     * @throws InvalidFormatException
     */
    public static function getRecentDate(Collection $all_dates, string|\DateTimeInterface|null $critical = null, bool $cast_as_carbon = false): mixed
    {
        // 預設臨界日期 = 今天
        $critical ??= now()->toDateString();

        // 將臨界日期解析為 CarbonImmutable 物件，且僅保留日期
        $critical_date = (($critical instanceof CarbonInterface) ? $critical : Carbon::parse($critical))->toImmutable()->startOfDay();

        // 濾除無效日期
        /** @var Collection $valid_dates */
        $valid_dates = $all_dates
            ->reduce(function (Collection $carry, CarbonInterface|DateTimeInterface|string|null $value, $key) {
                // 日期不能為空值
                if (
                    filled($value)
                    && (
                        ($value instanceof CarbonInterface) ||
                        ($value instanceof DateTimeInterface) ||
                        // 字串型的日期格式必須為 2021-01-01、2021-1-1、2021/01/01、2021/1/1、20210101 等
                        (is_string($value) && (strlen($value) > 1) && preg_match('/^(?:\d{4}[\\/-]\d{1,2}[\\/-]\d{1,2}|\d{8})$/', $value))
                    )
                ) {
                    // 將日期解析為 CarbonImmutable 物件，且僅保留日期
                    $date = (($value instanceof CarbonInterface) ? $value : Carbon::parse($value))->toImmutable()->startOfDay();

                    // 存入有效日期
                    $carry->put($key, $date);
                }

                return $carry;
            }, collect());

        // 未來日期
        $future_dates = $valid_dates
            ->filter(function (CarbonInterface $date) use ($critical_date) {
                // 只保留大於臨界日期的值
                return $date->gte($critical_date);
            });

        // 有未來日期優先取用，如無則取過去日期
        $key = $future_dates->sortBy(fn (CarbonInterface $date) => $date->getTimestamp(), descending: false)->keys()->first()
            ?? $valid_dates->sortBy(fn (CarbonInterface $date) => $date->getTimestamp(), descending: true)->keys()->first();

        // 取得最近日期
        $recent_date = $all_dates->get($key);

        // 回傳 Carbon 物件
        if ($cast_as_carbon) {
            return (($recent_date instanceof CarbonInterface) ? $recent_date->toImmutable() : Carbon::parse($recent_date))->startOfDay();
        }

        // 或者回傳原始資料
        return $recent_date;
    }

    /**
     * 將 \n 換行符號轉換為 <br>，但不會將 null 轉換為空字串
     * 且若 $force_null 為 true 時，則會將空字串也轉換為 null
     *
     * @param string|null $string  要轉換的字串
     * @param bool $escape  （選填）是否將特殊字元轉換為 HTML 實體，預設值為 false
     * @param bool $force_null  （選填）是否強制回傳 null，預設值為 false
     * @return string|null
     */
    public static function nl2br(?string $string, bool $escape = false, bool $force_null = false): ?string
    {
        if (is_null($string) || ($force_null && blank($string))) {
            return null;
        }

        // 將特殊字元轉換為 HTML 實體
        if ($escape) {
            $string = e($string);
        }

        return nl2br($string, false);
    }

    /**
     * 產生隨機大寫英文字母
     * @param int $count
     * @return string
     */
    public static function randomAlpha(int $count = 1): string
    {
        $str   = '';
        $count = max(1, $count);

        for ($i = 0; $i < $count; ++$i) {
            $str .= chr(mt_rand(65, 90));
        }

        return $str;
    }

    public static function PhoneMask($phone)
    {
        //加密
        // if (strlen($phone) > 50) {
        //    try {
        //     $decrypted = Crypt::decrypt($phone);
        //     } catch (\DecryptException $e) {
        //         Log::error($e);
        //         echo $e->getMessage();
        //     }
        // } else {
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
        $user = User::where('enumber', $enumber)->first();
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
                case 'App\Models\CRM\Obstacle'://客訴
                    $data   = Obstacle::withTrashed()->find($data->bindable_id);
                    $status = $data->status;

                    if ($status < 10) { // 結案或觀察後異常
                        return false;
                    }
                    break;

                case \App\Models\CRM\Common\Detect::class://檢測or維修
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

            if ($device_warranty && $warranty_status != 1) { // 判斷設備原廠保固 'CUSTOM'
                if (strtotime($device_warranty) < strtotime($todate)) { // 設備原廠過保
                    $warranty_status = 2;
                }
            }
        } catch (\Exception $e) {
            Log::error($e);
        }

        return $warranty_status;
    }

    public static function ToolTip($lang, $position = 'right', $ml = '0') // 燈泡提示
    {
        $res = "<i class='fas fa-lightbulb text-hover-warning ml-{$ml} mt-1' data-toggle='tooltip' data-html='true' data-placement='{$position}' title='{$lang}'></i>";

        return $res;
    }

    // info 提示
    public static function infoTooltip($title, $type = 'info-circle', $color = 'muted', $hover = '', $position = 'right', $style = '')
    {
        $icon_class = match ($type) {
            'info-circle' => 'fas fa-info-circle',
            'lightbulb'   => 'fas fa-lightbulb',
            default       => 'fas fa-info',
        };

        $color_class = 'text-' . $color;

        $hover_class = match ($hover) {
            'warning' => 'text-hover-warning',
            default   => '',
        };

        $style = strlen($style) ? 'style ="' . $style . '"' : '';

        return sprintf('<i class="%s %s %s align-middle" data-toggle="tooltip" data-html="true" data-placement="%s" title="%s" %s></i>', $icon_class, $color_class, $hover_class, $position, preg_replace('/"/', '&quot;', $title), $style);
    }

    public static function HasDeviceRecord(
        $serial_number = null,
        $device_id = null
    ) { // 檢查有序號設備是否報修中，決定是否可以新增DeviceRecord
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
        if (preg_match(
            '/^[-A-Za-z0-9_]+[-A-Za-z0-9_.]*[@]{1}[-A-Za-z0-9_]+[-A-Za-z0-9_.]*[.]{1}[A-Za-z]{2,5}$/',
            $mail
        )) {
            return true;
        }

        return false;
    }

    // Datatable開關UI
    public function SwitchButton($id, $status): string
    {
        $check = $status == 2 ? 'checked' : '';
        $res   = "<div class='switch' style='display:inline-block;'>
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
     *
     * @param  object  $model  有跟 Issues 關聯的 Model
     * @param  string  $document_number  Model 的單號 (進階搜尋使用)
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
     *
     * @param  string  $str  字串
     * @param  int  $limmit  限制字元長度
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
     * 數字轉中文字 （最多到億）
     * @param $num
     * @return string
     */
    public static function numToWord($num)
    {
        $chiNum    = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        $chiUni    = ['', '十', '百', '千', '萬', '十', '百', '千', '億'];
        $num_str   = (string) $num;
        $count     = strlen($num_str);
        $last_flag = true; //上一個 是否為0
        $zero_flag = true; //是否第一個
        $chiStr    = ''; //拼接結果
        if ($count == 2) {//兩位數
            $temp_num = $num_str[0];
            $chiStr   = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num] . $chiUni[1];
            $temp_num = $num_str[1];
            $chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
        } else {
            if ($count > 2) {
                $index = 0;
                for ($i = $count - 1; $i >= 0; --$i) {
                    $temp_num = $num_str[$i];
                    if ($temp_num == 0) {
                        if (!$zero_flag && !$last_flag) {
                            $chiStr    = $chiNum[$temp_num] . $chiStr;
                            $last_flag = true;
                        }

                        if ($index == 4 && $temp_num == 0) {
                            $chiStr = '萬' . $chiStr;
                        }
                    } else {
                        if ($i == 0 && $temp_num == 1 && ($index == 1 || $index == 5)) {
                            $chiStr = $chiUni[$index % 9] . $chiStr;
                        } else {
                            $chiStr = $chiNum[$temp_num] . $chiUni[$index % 9] . $chiStr;
                        }
                        $zero_flag = false;
                        $last_flag = false;
                    }
                    ++$index;
                }
            } else {
                $chiStr = $chiNum[$num_str[0]];
            }
        }

        return $chiStr;
    }

    /**
     * 日期/時間格式化
     * @param string|\DateTimeInterface|null $date   日期/時間
     * @param string                         $format 格式
     * @return string|null
     */
    public static function formatDateTime(string|\DateTimeInterface|null $date, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return $date?->format($format);
    }

    /**
     * 保留小數點第二位，遇到尾數零自動排除
     * @param $num
     * @return string
     */
    public static function formatNumber($num)
    {
        $num = number_format((float) $num, 2, '.', '');
        $num = rtrim($num, '0');
        $num = rtrim($num, '.');
        if (substr($num, -1) == '.') {
            $num = substr($num, 0, -1);
        }

        return $num;
    }

    /**
     * 日期格式轉換 20220101 -> 2022/01/01
     * @param ?string  $date   日期
     * @param string   $format 格式
     * @return \DateTime
     */
    public static function strictParseDateTime(?string $date): ?\DateTime
    {
        if (blank($date) || (is_string($date) && (strlen($date) === 1))) {
            return null;
        }

        $format_list = [
            'Y-m-d',
            'Ymd',
            'Y/m-j',
            'Y-m/j',
            'Ym/j',
        ];

        $format_list = array_merge($format_list, array_map(fn (string $format) => str_replace('-', '/', $format), $format_list));

        foreach ($format_list as $format) {
            if ($datetime = \DateTime::createFromFormat($format, trim($date))) {
                return $datetime;
            }
        }

        return null;
    }

    /**
     * 日期格式轉換 20220101 -> 2022/01/01
     * @param CarbonInterface|\DateTimeInterface|null $date   日期
     * @param string      $format 格式
     */
    public static function parseDateTime(CarbonInterface|\DateTimeInterface|string|null $date): ?CarbonInterface
    {
        if (blank($date) || (is_string($date) && (strlen($date) === 1))) {
            return null;
        }

        return Carbon::parse($date);
    }

    /**
     * 日期格式轉換 20220101 -> 2022/01/01
     * @param string|null $date   日期
     * @return Carbon|null
     *
     * @throws InvalidFormatException
     */
    public static function parseSapDate(?string $date): ?Carbon
    {
        if (blank($date) || $date === '00000000' || !($date = Carbon::createFromFormat('Ymd', $date))) {
            return null;
        }

        return $date;
    }

    /**
     * 日期格式轉換 20220101 -> 2022-01-01
     * @param string|null $date   日期
     * @param string      $format 格式
     */
    public static function formatSapDate(?string $date, string $format = 'Y-m-d', bool $fallback = false): ?string
    {
        // TODO: Amy 之前說希望日期統一為 YYYY/MM/DD，而不是 YYYY-MM-DD
        // e.g. format = config('app.format.date')
        return static::parseSapDate($date)?->format($format) ?? ($fallback ? $date : null);
    }

    /**
     * 判斷變數是否為純數值
     * @param mixed $value  來源值
     * @param bool $strict （可選）嚴格判斷，型別必須為 int 或 float，不能是 string。預設值：false
     * @return bool
     */
    public static function isNumeric(mixed $value, bool $strict = false): bool
    {
        $is_numeric = is_int($value) || is_float($value);

        // 嚴格模式，不能是包含純數字的 string
        if ($strict) {
            return $is_numeric;
        }

        // 寬鬆模式，可以是包含純數字的 string
        if (!$is_numeric) {
            $is_numeric = !is_bool($value) && (
                (filter_var($value, FILTER_VALIDATE_INT) !== false)
                || (filter_var($value, FILTER_VALIDATE_FLOAT) !== false)
            );
        }

        return $is_numeric;

        /*
        // 嚴格模式，不能是包含純數字的 string
        if ($strict) {
            return is_int($value) || is_float($value);
        }

        // 寬鬆模式，可以是包含純數字的 string
        return !is_bool($value) && (
               (filter_var($value, FILTER_VALIDATE_INT) !== false)
            || (filter_var($value, FILTER_VALIDATE_FLOAT) !== false)
        );
        */
    }

    /**
     * 去除純數值字串的首尾 0、空白
     * @param int|float|string $value  來源值
     * @param string $decimal_separator
     * @return int|float
     *
     * @throws \ValueError
     */
    public static function trimNumeric(int|float|string $value, $decimal_separator = '.'): int|float
    {
        if (!static::isNumeric($value, false)) {
            throw new \ValueError('The value is not numeric: ' . $value);
        }

        // 移除字串首尾空白（例如：' 10 '）、科學記號轉換為數值字串（例如：'1e2'） → 移除末端多餘數字 0（例如：10.00） → 移除末端多餘小數點（例如：10.）
        $value = trim((string) +$value);
        $value = preg_replace('/^(.*' . preg_quote($decimal_separator, '/') . '.*?)0+$/', '$1', $value);
        $value = rtrim($value, '.');

        return +$value;
    }

    /**
     * 對數值進行千分位格式化
     * @param mixed $value     來源值
     * @param int $max_digits  最大小數位數
     * @param bool $fallback  （可選）傳入無效值時，是否保留原值，否則回傳 null。預設值：false
     * @return string|null|mixed
     */
    public static function formatDecimal(mixed $value, int $max_digits = -1, bool $fallback = false): mixed
    {
        // 支援陣列格式批次 format
        if (is_iterable($value)) {
            foreach ($value as $sub_key => $sub_value) {
                $value[$sub_key] = static::{__FUNCTION__}($sub_value, $max_digits, $fallback);
            }

            return $value;
        }

        // 有效值則格式化千分位
        // （但 number_format 預設會四捨五入，需先計算小數位數後，再進行 format）
        if (static::isNumeric($value, false)) {
            $value      = static::trimNumeric($value);
            $digits_len = strlen(substr(strrchr((string) $value, '.'), 1));
            $max_digits = ($max_digits !== -1) ? min($digits_len, $max_digits) : $digits_len;

            try {
                return number_format(+$value, $max_digits);
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        // 無效值回傳 null 或原始值
        return $fallback ? $value : null;
    }

    /**
     * 將數值轉為百分比
     * @param mixed $value       來源值
     * @param int   $max_digits  最大小數位數
     * @param bool  $fallback    （可選）傳入無效值時，是否保留原值，否則回傳 null。預設值：false
     * @param bool  $fullwidth   （可選）百分比符號是否用全形，預設值：false
     * @return string|null|mixed
     */
    public static function formatPercent(mixed $value, int $max_digits = -1, bool $fallback = false, bool $fullwidth = false): mixed
    {
        // 支援陣列格式批次 format
        if (is_iterable($value)) {
            foreach ($value as $sub_key => $sub_value) {
                $value[$sub_key] = static::{__FUNCTION__}($sub_value, $max_digits, $fallback, $fullwidth);
            }

            return $value;
        }

        // 有效值則加上百分比符號，無效值回傳 null 或原始值
        /*
        return (is_string($value) && filled($value))
            ? $value . ($fullwidth ? '％' : '%')
            : ($fallback ? $value : null);
        */

        // 有效值則格式化千分位，並加上百分比符號
        // （但 number_format 預設會四捨五入，需先計算小數位數後，再進行 format）
        if (static::isNumeric($value, false)) {
            // 先格式化千分位
            $value = static::formatDecimal($value, $max_digits, $fallback);

            // 有效值則加上百分比符號，無效值回傳 null 或原始值
            return $value . ($fullwidth ? '％' : '%');
        }

        // 無效值回傳 null 或原始值
        return $fallback ? $value : null;
    }

    // 倒轉版 array_search()
    public static function array_search_last(mixed $needle, array|\Iterator $haystack, bool $strict): int|string|false
    {
        end($haystack);
        while (($key = key($haystack)) !== null) {
            $value = current($haystack);
            $found = $strict ? ($value === $needle) : ($value == $needle);
            if ($found) {
                return $key;
            }
            prev($haystack);
        }

        return false;
    }

    /**
     * 判斷是否為合法字元
     * @param string $char  字元
     * @return bool
     */
    public static function isCharacter(string $char): bool
    {
        $c = ord($char);

        // 控制字元 = 0x00-0x08, 0x0B-0x1F, 0x7F, 0x80-0x9F
        return ($c < 0x100)
            ? (in_array($c, [0x9, 0xA, 0xD]) || ($c >= 0x20))
            : (($c >= 0x100 && $c <= 0xD7FF) || ($c >= 0xE000 && $c <= 0xFFFD) || ($c >= 0x10000 && $c <= 0x10FFFF));
    }

    /**
     * 排除 Control Characters (0x00-0x08, 0x0B-0x1F, 0x7F, 0x80-0x9F)
     *
     * @param  mixed  $input
     * @param  bool  $removeC1  是否移除 C1 控制字元（0x80-0x9F），即 Latin-1（ISO 8859-1）未定義字元
     * @return mixed
     */
    public static function removeControlCharacters(mixed $input, bool $removeC1 = true): mixed
    {
        if (!is_string($input)) {
            return $input;
        }

        if (!mb_check_encoding($input, 'UTF-8')) {
            logs()->warning('removeControlCharacters: invalid UTF-8 string', ['input' => $input]);

            return $input;
        }

        $npcRange = '\x{0000}-\x{0008}\x{000b}-\x{001f}\x{007f}';
        if ($removeC1) {
            $npcRange .= '\x{0080}-\x{009f}';
        }

        // 控制字元：0x00-0x08, 0x0B-0x1F, 0x7F, 0x80-0x9F
        // 其中，ASCII 中的 0x0-0x8 及 0xB-0x1F 會直接導致 ErrorException: DOMDocument::loadHTML(): Invalid char in CDATA 0xB in Entity 等錯誤（0x8、0xB 等較常見）
        // 而 0x7F（DEL 字元）及 0x80-0x9F（ISO 8859-1 未定義字元）則不會
        return preg_replace("/[{$npcRange}]/u", '', $input);
        // return preg_replace('/[\x{0000}-\x{0008}\x{000b}-\x{001f}\x{007f}\x{0080}-\x{009f}]/u', '', $input);
    }

    /**
     * 判斷number_format的值是不是數字 並給千分位
     */
    public static function numBox($input)
    {
        return static::isNumeric($input, true)
            ? number_format($input)
            : $input;
    }

    /**
     * 從Collection中取得 某年-月 資料
     * @param $data
     * @param int $year
     * @param int $month
     * @param string $key
     * @return Collection
     */
    public static function getYearMonthData($data, $year, $month, $key = 'date')
    {
        return collect($data)
            ->filter(function ($row) use ($year, $month, $key) {
                $date = Carbon::parse($row[$key]);

                return $date->year == $year && $date->month == $month;
            });
    }

    /**
     * 將「含稅」價 轉成「未稅」價
     *
     * @param  mixed  $val  float|int 值
     * @param  int|null  $tax_type  int 稅別
     * @param  float|null  $tax_tate  float 稅率
     * @param  int|null  $precision  int 小數點位
     * @return mixed
     */
    public static function toUnTaxedVal(
        mixed $val,
        $tax_type = ProjectHeaderRepository::TAX_TYPE_TAXABLE,
        $tax_tate = ProjectHeaderRepository::TAX_RATE,
        ?int $precision = 0
    ): mixed {
        if (is_numeric($val) && $tax_type == ProjectHeaderRepository::TAX_TYPE_TAXABLE) {
            return round($val / (1 + $tax_tate), $precision);
        }

        return $val;
    }

    /**
     * 將「未稅」價 轉成「含稅」價
     *
     * @param  mixed $val  int|float 值
     * @param  int|null  $tax_type  int 稅別
     * @param  float|null  $tax_tate  float 稅率
     * @param  int|null  $precision  int 小數點位
     * @return mixed
     */
    public static function toTaxVal(
        mixed $val,
        $tax_type = ProjectHeaderRepository::TAX_TYPE_TAXABLE,
        $tax_tate = ProjectHeaderRepository::TAX_RATE,
        ?int $precision = 0
    ): mixed {
        if (is_numeric($val) && $tax_type == ProjectHeaderRepository::TAX_TYPE_TAXABLE) {
            return round($val * (1 + $tax_tate), $precision);
        }

        return $val;
    }

    /**
     * 計算幾小時後是幾點
     *
     * @param DateTime $startTime 要計算的時間
     * @param float $hoursToAdd 幾小時後
     * @return DateTime
     * @throws \Exception
     */
    public static function addHoursToTime($startTime, $hoursToAdd)
    {
        $resultTime = new DateTime($startTime);

        // Convert hours to minutes
        $minutesToAdd = $hoursToAdd * 60;

        if ($minutesToAdd) {
            // Create a new DateTime object for modification
            $modifiedTime = clone $resultTime;

            // Add the specified minutes
            $modifiedTime->modify('+' . $minutesToAdd . ' minutes');

            // Get the result as a string
            $resultTimeString = $modifiedTime->format('Y-m-d H:i:s');
        } else {
            // If no minutes to add, keep the original time
            $resultTimeString = $resultTime->format('Y-m-d H:i:s');
        }

        return $resultTimeString;
    }

    /**
     * 消毒檔案名稱
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename, bool $with_dir = false, int $max_length = 200): string
    {
        // 檔名長度上限
        $max_length = max(1, $max_length);

        // 完整檔名如包含路徑，僅保留檔名部分
        if ($with_dir) {
            $filename = basename($filename);
        }

        // 移除無效字元，但保留點和斜線（因需要處理檔案副檔名）
        $clean = preg_replace('/[<>:"\/\\\|\?\*\']+/', '', $filename);

        // 空格換成底線
        $clean = str_replace(' ', '_', $clean);

        // 移除不可見的控制字元
        $clean = static::removeControlCharacters($clean);

        // 限制檔名長度
        $clean = mb_substr($clean, 0, $max_length);

        return $clean;
    }

    /** 轉換為excel report用百分比 (除100)*/
    public static function excel_format_percentage_00($val, $default = null)
    {
        return is_numeric($val)
            ? $val / 100
            : ($default ?? $val);
    }

    /**
     * 取得有Cached的部門資訊
     * @param  array  $arr_layers
     * @param  bool  $with_users_count
     * @return Collection
     * @throws InvalidArgumentException
     */
    public static function getCachedDepts(array $arr_layers = ['1', '2', '3', '4'], bool $with_users_count = false, bool $key_by_code = true)
    {
        $cache = Cache::driver('array');
        $key   = 'cached_depts_'
            . ($with_users_count ? 'with_count' : 'no_count')
            . '_' . join('_', $arr_layers)
            . ($key_by_code ? 'by_code' : 'not_by_code');

        if ($cache->has($key)) {
            return $cache->get($key);
        }
        $data = Dept::query()
            ->whereIn('layer', $arr_layers)
            ->when($with_users_count, fn ($q) => $q->withCount('users')) // 會有 users_count 欄位
            ->get()
            ->when($key_by_code, fn ($data) => $data->keyBy('code'));

        $cache->put($key, $data);

        return $data;
    }
}
