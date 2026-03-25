<?php

namespace App\Libraries;

use App\Models\CRM\Common\CRMDocumentCount;
use App\Models\DocumentCount;
use App\Models\Files;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Lang;

class EntrustLib
{
    public static function DeleteButton($id, $name = '', $type = '')
    {
        $del = Lang::get('button.delete');
        $res = "<div class='row justify-content-center'>
                    <button class='btn btn-icon btn-circle btn-light-danger font-weight-bolder' link='{$id}' data-type='{$type}' onclick='Delete{$name}(this)' data-toggle='tooltip' title='{$del}'>
                        <i class='fas fa-trash-alt'></i>
                    </button>
                </div>";

        return $res;
    }

    public static function RestoreButton($id, $name = '')
    {
        $del = Lang::get('button.restore');
        $res = "<div class='row justify-content-center'>
                    <button class='btn btn-icon btn-circle btn-light-info font-weight-bolder' link='{$id}' onclick='Restore{$name}(this)' data-toggle='tooltip' title='{$del}'>
                        <i class='fas fa-box-open'></i>
                    </button>
                </div>";

        return $res;
    }

    public static function DocumentNumber($status, $sn = null, $system = 'hws')
    {
        $date    = date('ymd');
        $date_ym = date('Ym');
        if ($system === 'hws') {
            $document = DocumentCount::query()->lockForUpdate()->sole();
            $prefix   = tenant()->document_code;
        } else {
            $document = CRMDocumentCount::find(1);
        }


        switch ($status) {
            case 'clock_header':
            case 'clock': // 工時
                $count           = $document->clock + 1;
                $document->clock = $count;
                $prefix          = ($prefix ?? '') . 'H';    // 華電 = Hyyyymmddnnnn；開啟資安 = OHyyyymmddnnnn
                $document_number = $prefix . $date . sprintf('%04d', $count);
                break;

            case 'project_header': // Case Header
                // TODO: ...
                $prefix                   = ($prefix ?? '') . 'C';    // 華電 = Cyyyymmnnn；開啟資安 = OCyyyymmnnn
                $auto_num_length          = 3;
                $count                    = $document->project_header + 1;
                $document->project_header = $count;
                $document_number          = sprintf("%s%s%0{$auto_num_length}d", $prefix, $date_ym, $count);
                if (strlen((string) $count) > $auto_num_length) {
                    throw new \ErrorException('當月 Case 編號已佔滿。最多 ' . (10 ** $auto_num_length - 1) . ' 筆。');
                }
                break;

            case 'sign_off': //簽核線
                throw_if(is_null($sn), '簽核線單號需要流水號。');
                // $count              = $document->sign_off + 1;
                // $document->sign_off = $count;
                $prefix          = ($prefix ?? '') . 'V';    // 華電 = Vyyyymmddnnnnn；開啟資安 = OVyyyymmddnnnnn
                $document_number = $prefix . date('ym') . $sn;    // $type . sprintf('%05d', $count)
                break;

            // CRM
            case 'contact': // 申告
                $count             = $document->contact + 1;
                $document->contact = $count;
                $document_number   = 'B' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'obstacle': // 客訴
                $count            = $document->report + 1;
                $document->report = $count;
                $document_number  = 'T' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'dispatch': // 派工
                $count              = $document->dispatch + 1;
                $document->dispatch = $count;
                $document_number    = 'J' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'detect': // 檢測
                $count            = $document->detect + 1;
                $document->detect = $count;
                $document_number  = 'D' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'repair': // 維修
                $count            = $document->repair + 1;
                $document->repair = $count;
                $document_number  = 'R' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'maintain': // 定保
                $count              = $document->maintain + 1;
                $document->maintain = $count;
                $document_number    = 'M' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'event':
                $count           = $document->event + 1;
                $document->event = $count;
                $document_number = 'E' . $date . sprintf('%03d', $count);
                break;

            // CRM
            case 'job_ticket': //工聯單號 CRM-890 區分開啟華電的工聯單
                $instance        = new self();
                $document_number = $instance->createTicketNumber();
                break;

            // CRM
            case 'dispatch_vendor': // 廠商派工
                $count                     = $document->dispatch_vendor + 1;
                $document->dispatch_vendor = $count;
                $document_number           = 'V' . $date . sprintf('%03d', $count);

            // CRM
            // no break
            case 'issue': // 需求單號
                $count           = $document->issue + 1;
                $document->issue = $count;
                $document_number = 'I' . $date . sprintf('%03d', $count);
        }

        $document->save();

        return $document_number;
    }

    public static function Progress($progress)
    {
        $bg = 'warning';
        switch ($progress) {
            case $progress < 50:
                $bg = 'warning';
                break;

            case $progress < 100:
                $bg = 'success';
                break;

            case 100:
                $bg = 'primary';
                break;
        }

        $res = "<div class='progress'><div class='progress-bar progress-bar-striped progress-bar-animated bg-{$bg}' role='progressbar' style='width:{$progress}%' aria-valuenow='10' aria-valuemin='0' aria-valuemax='100'></div></div>";

        return $res;
    }

    public static function CheckButton($id, $status = 0, $function_name = 'check')
    {
        $color  = 'btn-outline-danger';
        $button = '<i class="fas fa-times"></i>';
        if ($status == 0) {
            $color  = 'btn-outline-danger';
            $button = '<i class="fas fa-times"></i>';
        } elseif ($status == 1) {
            $color  = 'btn-light-primary';
            $button = '<i class="fas fa-check"></i>';
        }
        // elseif ($status == 2) {
        //     $color = "btn-light-warning";
        //     $button = "<i class='fas fa-exclamation'></i>";
        // }

        $res = "<div class='row justify-content-center'><button class='btn btn-icon btn-circle btn-sm {$color}' id='{$function_name}button{$id}' onclick='{$function_name}({$id})'>{$button}</button></div>";

        return $res;
    }

    public static function FileButton($id, $status, $name = '')
    {
        $res      = '';
        $delete   = Lang::get('button.delete');
        $handle   = Lang::get('button.handle');
        $download = Lang::get('button.download');
        $error    = Lang::get('common.error');
        $file     = Files::find($id);
        $id       = Crypt::encryptString($id);

        if ($status == 0) { // 處理中
            $res .= "<div class='row justify-content-center'>
                        <button disabled class='btn btn-light-success font-weight-bolder'>
                            <div class='spinner-border spinner-border-sm text-success mr-2' role='status'>
                                <span class='sr-only'>Loading...</span>
                            </div>
                            {$handle} . . .
                        </button>
                    </div>";
        } elseif ($status == 1) { // 下載
            $res .= "<div class='row justify-content-center'>
                        <a class='btn btn-light-primary font-weight-bolder' link_id='{$id}' onclick='{$name}downLoad(this)'>
                            <i class='fas fa-download'></i>{$download}
                        </a>
                        <button class='btn btn-light-danger font-weight-bolder ml-2' link_id='{$id}' onclick='{$name}FileDelete(this)'>
                            <i class='fas fa-trash-alt'></i>{$delete}
                        </button>
                    </div>";
        } elseif ($status == 2) { // 錯誤
            $res .= "<div class='row justify-content-center'>
                        <span class='label label-lg font-weight-bolder label-light-warning label-inline justify-content-center' style='margin-top: 8px;'>
                            <i class='fas fa-error'></i>{$error}
                        </span>
                        <button class='btn btn-light-danger font-weight-bolder ml-1' link_id='{$id}' onclick='{$name}FileDelete(this)'>
                            <i class='fas fa-trash-alt'></i>{$delete}
                        </button>
                    </div>";
        }

        return $res;
    }

    /**
     * 一次建立多筆單號
     *
     * @param string $status 單號類型
     * @param int $num 建立筆數
     * @return array
     */
    public static function createMultipleDocumentNumber($status, $num = null)
    {
        $date            = date('ymd');
        $date_ym         = date('Ym');
        $document        = DocumentCount::lockForUpdate()->find(1);
        $document_number = [];
        switch ($status) {
            case 'clock': // 工時母單
                for ($i = 1; $i <= $num; ++$i) {
                    $count             = $document->clock + $i;
                    $document_number[] = 'H' . $date . sprintf('%04d', $count);
                }
                $document->clock = $document->clock + $num;
                break;
        }

        $document->save();

        return $document_number;
    }
}
