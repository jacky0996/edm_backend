<?php

namespace App\Libraries;

use App\Models\Area;
use App\Models\CRM\Common\CRMDocumentCount;
use App\Models\ERM\JobTicket;
use App\Models\Files;
use Illuminate\Support\Facades\Lang;

class CRMEntrustLib
{
    public static function DeleteButton($id, $name = '', $type = '')
    {
        $del = Lang::get('button.delete');
        $res = "<div class='row justify-content-center'><button class='btn btn-icon btn-circle btn-light-danger font-weight-bolder' link='{$id}' data-type='{$type}' onclick='Delete{$name}(this)'><i class='fas fa-trash-alt'></i></button></div>";

        return $res;
    }

    public static function RestoreButton($id, $name = '')
    {
        $del = Lang::get('button.restore');
        $res = "<div class='row justify-content-center'><button class='btn btn-icon btn-circle btn-light-info font-weight-bolder' link='{$id}' onclick='Restore{$name}(this)'><i class='fas fa-box-open'></i></button></div>";

        return $res;
    }

    public static function Area($id)
    {
        try {
            $area = Area::find($id)->name;
        } catch (\Exception $e) {
            $area = $id;
        }

        return $area;
    }

    public static function DocumentNumber($status)
    {
        $date     = date('ymd');
        $document = CRMDocumentCount::find(1);

        switch ($status) {
            case 'contact': // 申告
                $count             = $document->contact + 1;
                $document->contact = $count;
                $document_number   = 'B' . $date . sprintf('%03d', $count);
                break;

            case 'obstacle': // 客訴
                $count            = $document->report + 1;
                $document->report = $count;
                $document_number  = 'T' . $date . sprintf('%03d', $count);
                break;

            case 'dispatch': // 派工
                $count              = $document->dispatch + 1;
                $document->dispatch = $count;
                $document_number    = 'J' . $date . sprintf('%03d', $count);
                break;

            case 'detect': // 檢測
                $count            = $document->detect + 1;
                $document->detect = $count;
                $document_number  = 'D' . $date . sprintf('%03d', $count);
                break;

            case 'repair': // 維修
                $count            = $document->repair + 1;
                $document->repair = $count;
                $document_number  = 'R' . $date . sprintf('%03d', $count);
                break;

            case 'maintain': // 定保
                $count              = $document->maintain + 1;
                $document->maintain = $count;
                $document_number    = 'M' . $date . sprintf('%03d', $count);
                break;

            case 'event':
                $count           = $document->event + 1;
                $document->event = $count;
                $document_number = 'E' . $date . sprintf('%03d', $count);
                break;

            case 'deposit': // 保證金
                $count             = $document->deposit + 1;
                $document->deposit = $count;
                $document_number   = 'DA' . $date . sprintf('%02d', $count);
                break;

            case 'job_ticket': //工聯單號 CRM-890 區分開啟華電的工聯單
                $instance        = new self();
                $document_number = $instance->createTicketNumber();
                break;

            case 'dispatch_vendor': // 廠商派工
                $count                     = $document->dispatch_vendor + 1;
                $document->dispatch_vendor = $count;
                $document_number           = 'V' . $date . sprintf('%03d', $count);

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
        $button = '<i class=\'fas fa-times\'></i>';
        if ($status == 0) {
            $color  = 'btn-outline-danger';
            $button = '<i class=\'fas fa-times\'></i>';
        } elseif ($status == 1) {
            $color  = 'btn-light-primary';
            $button = '<i class=\'fas fa-check\'></i>';
        }
        $res = "<div class='row justify-content-center'><button class='btn btn-icon btn-circle btn-sm {$color}' id='{$function_name}button{$id}' onclick='{$function_name}({$id})'>{$button}</button></div>";

        return $res;
    }

    public static function FileButton($id, $name = '', $status = null)
    {
        $res      = '';
        $delete   = Lang::get('button.delete');
        $handle   = Lang::get('button.handle');
        $download = Lang::get('button.download');
        //$file       = Files::find($id);

        if ($status == 0) { // 處理中
            $res .= "<div class='row'>
                        <button disabled class='btn btn-light-success font-weight-bolder'>
                            <div class='spinner-border spinner-border-sm text-success mr-2' role='status'>
                                <span class='sr-only'>Loading...</span>
                            </div>
                            {$handle} . . .
                        </button>
                    </div>";
        } elseif ($status == 1) { // 下載
            $res .= "<div class='row'>
                        <a class='btn btn-light-primary font-weight-bolder' link_id='{$id}' onclick='downLoad(this)'>
                            <i class='fas fa-download'></i>{$download}
                        </a>
                        <button class='btn btn-light-danger font-weight-bolder ml-2' link_id='{$id}' onclick='{$name}CRMFileDelete(this)'>
                            <i class='fas fa-trash-alt'></i>{$delete}
                        </button>
                    </div>";
        } elseif ($status == 2) { // 錯誤
            $res .= "<div class='row'>
                        <button class='btn btn-light-danger font-weight-bolder' link_id='{$id}' onclick='{$name}CRMFileDelete(this)'>
                            <i class='fas fa-trash-alt'></i>{$delete}
                        </button>
                    </div>";
        }

        return $res;
    }

    //CRM-890為了區分華電與開啟的工聯單，額外建立Function判別
     private function createTicketNumber()
     {
        $corp_hrm_code = auth()->user()->corp->corp_hrm_code; //HTWA,HTWB,HTWC,HTWD,HTWE.....
        $date          = date('ymd');
        switch ($corp_hrm_code) {
            case 'HTWE': //開啟資安
                $latest = JobTicket::where('ticket_number', 'like', 'OW' . $date . '%')
                    ->orderByDesc('ticket_number')
                    ->value('ticket_number');
                $lastNumber    = $latest ? sprintf('%03d', substr($latest, -3) + 1) : '001';
                $ticket_number = 'OW' . $date . $lastNumber;
                break;

            default: //非開啟同仁建立的一律跟華電相同W
                $document             = CRMDocumentCount::find(1);
                $count                = $document->job_ticket + 1;
                $document->job_ticket = $count;
                $ticket_number        = 'W' . $date . sprintf('%03d', $count);
                $document->save();
        }

        return $ticket_number;
    }
}
