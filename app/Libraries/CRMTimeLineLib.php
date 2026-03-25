<?php

namespace App\Libraries;

use App\Models\CRM\Common\CRMProjectTimeLine;
use App\Models\CRM\Common\MaintainTimeLine;
use App\Models\CRM\Common\ObsTimeLine;
use App\Presenters\CRM\CRMProjectPresenter;
use App\Presenters\CRM\ObstaclePresenter;
use Illuminate\Support\Facades\Crypt;

class CRMTimeLineLib
{
   //TODO:TimeLine API
    public static function pushMaintainMiss($data) //定保逾期
    {
        $line             = new MaintainTimeLine();
        $line->project_id = $data['project_id'] ?? null;
        $line->user_id    = $data['user_id']    ?? null;
        $line->aname      = $data['aname']      ?? null;
        $line->alink      = $data['alink']      ?? null;
        $line->context    = $data['context']    ?? null;
        $line->status     = $data['status']     ?? null;
        $line->save();
    }

    public static function pushProjectTimelime($data)
    {
        $line                = new CRMProjectTimeLine();
        $line->project_id    = $data['project_id']    ?? null;
        $line->user_id       = $data['user_id']       ?? null;
        $line->aname         = $data['aname']         ?? null;
        $line->alink         = $data['alink']         ?? null;
        $line->context       = $data['context']       ?? null;
        $line->status        = $data['status']        ?? null;
        $line->handle_status = $data['handle_status'] ?? null;
        $line->save();
    }

    public static function pushLine($data)
    {
        $line              = new ObsTimeLine();
        $line->obstacle_id = $data['obstacle_id'] ?? null;
        $line->user_id     = $data['user_id']     ?? null;
        $line->aname       = $data['aname']       ?? null;
        $line->alink       = $data['alink']       ?? null;
        $line->context     = $data['context']     ?? null;
        $line->status      = $data['status']      ?? null;
        $line->save();
    }

    public static function OverdueLine($data)
    {
        $line              = new ObsTimeLine();
        $line->type        = 0;
        $line->obstacle_id = $data['obstacle_id'] ?? null;
        $line->user_id     = $data['user_id']     ?? null;
        $line->aname       = $data['aname']       ?? null;
        $line->alink       = $data['alink']       ?? null;
        $line->context     = $data['context']     ?? null;
        $line->status      = $data['status']      ?? null;
        $line->save();
    }

    public static function createLine($id)
    {
        $line  = null;
        $items = ObsTimeLine::where('obstacle_id', $id)->orderby('created_at')->get();
        $line  = '<div class=\'timeline timeline-6 mt-3\'>';
        $date  = null;

        foreach ($items as $item) {
            if ($date != $item->created_at->format('Y-m-d')) {
                $date = $item->created_at->format('Y-m-d');
                $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>
                                {$item->created_at->format('Y')}<br>
                                {$item->created_at->format('m/d')}<hr style='border-top: 3px solid #EBEDF3;' class='mt-2 mb-4'>
                                {$item->created_at->format('H:i')}
                            </div>";
            } else {
                $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>{$item->created_at->format('H:i')}</div>";
            }
            if ($item->type == 0) {
                $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-danger icon-xl\'></i>
                            </div>';
                $status = '<span class=\'label label-lg font-weight-bolder label-light-danger label-inline\'>逾期</span>';
                $line .= "<div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";
            } else {
                switch ($item->status) {
                    case 1: // 新建
                    case 2: // 待派小組長
                    case 3: // 待派工程師
                    case 4: // 待Callback
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-warning icon-xl\'></i>
                            </div>';
                        break;

                    case 5: // 待維修
                    case 6: // 待工程結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-success icon-xl\'></i>
                            </div>';
                        break;

                    case 7: // 待客戶驗收
                    case 8: // 待客服驗收
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-info icon-xl\'></i>
                            </div>';
                        break;

                    case 9: // 待結案
                    case 10: // 結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-primary icon-xl\'></i>
                            </div>';
                        break;

                    case 11: // 觀察後異常
                    case 12: // 業務不修結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-danger icon-xl\'></i>
                            </div>';
                        break;
                }
                $link   = $item->alink ?? '#';
                $status = ObstaclePresenter::status($item->status);
                $line .= "<div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";

                if ($item->alink && $item->alink) {
                    $line .= "<a href='{$link}' class='text-primary font-weight-bolder' target='_blank'>{$item->aname}</a><br>";
                }
            }

            $line .= "<span class='text-muted'>{$item->context}</span>
                        </div>
                    </div>";
        }

        $line .= '</div>';

        return $line;
    }

    public static function ProjectLine($project)
    {
        $line        = null;
        $items       = CRMProjectTimeLine::where('project_id', $project->id)->orderby('created_at')->get();
        $line        = '<div class=\'timeline timeline-6 mt-3\'>';
        $date        = null;
        $createdLine = CRMProjectTimeLine::where('project_id', $project->id)->where('status', 1)->where('handle_status', 0)->first();

        //預設塞入專案同步時間
        if (!$createdLine) {
            $date   = $project->created_at->format('Y-m-d');
            $status = CRMProjectPresenter::status(0);
            $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>
                                {$project->created_at->format('Y')}<br>
                                {$project->created_at->format('m/d')}<hr style='border-top: 3px solid #EBEDF3;' class='mt-2 mb-4'>
                                {$project->created_at->format('H:i')}
                            </div>
                            <div class='timeline-badge'>
                                <i class='fa fa-genderless text-warning icon-xl'></i>
                            </div><div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br><span class='text-muted'>新增專案-ERP同步</span>
                        </div>
                    </div>";
        }

        foreach ($items as $item) {
            if ($date != $item->created_at->format('Y-m-d')) {
                $date = $item->created_at->format('Y-m-d');
                $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>
                                {$item->created_at->format('Y')}<br>
                                {$item->created_at->format('m/d')}<hr style='border-top: 3px solid #EBEDF3;' class='mt-2 mb-4'>
                                {$item->created_at->format('H:i')}
                            </div>";
            } else {
                $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>{$item->created_at->format('H:i')}</div>";
            }
            if ($item->status == 0) {
                $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-danger icon-xl\'></i>
                            </div>';
                $status = '<span class=\'label label-lg font-weight-bolder label-light-danger label-inline\'>逾期</span>';
                $line .= "<div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";
            } else {
                switch ($item->handle_status) {
                    case 0: // 新建
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-warning icon-xl\'></i>
                            </div>';
                        break;

                    case 1: // 處理中
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-success icon-xl\'></i>
                            </div>';
                        break;

                    case 2: // 結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-primary icon-xl\'></i>
                            </div>';
                        break;

                    case 3: // 交接中
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-info icon-xl\'></i>
                            </div>';
                        break;

                    case 4: // 審核中
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-danger icon-xl\'></i>
                            </div>';
                        break;
                }
                $link   = $item->alink ?? '#';
                $status = CRMProjectPresenter::status($item->handle_status);
                $line .= "<div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";

                if ($item->alink && $item->alink) {
                    $line .= "<a href='{$link}' class='text-primary font-weight-bolder' target='_blank'>{$item->aname}</a><br>";
                }
            }

            $line .= "<span class='text-muted'>{$item->context}</span>
                        </div>
                    </div>";
        }

        $line .= '</div>';

        return $line;
    }

    /**
     * Vip客訴時間線
     *
     * @param $id
     * @return string
     */
    public static function createVipLine($id)
    {
        $line  = null;
        $items = ObsTimeLine::where('obstacle_id', $id)->orderby('created_at')->get();
        $line  = '<div class=\'timeline timeline-6 mt-3\'>';
        $date  = null;

        foreach ($items as $item) {
            if ($date != $item->created_at->format('Y-m-d')) {
                $date = $item->created_at->format('Y-m-d');
                $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>
                                {$item->created_at->format('Y')}<br>
                                {$item->created_at->format('m/d')}<hr style='border-top: 3px solid #EBEDF3;' class='mt-2 mb-4'>
                                {$item->created_at->format('H:i')}
                            </div>";
            } else {
                $line .= "<div class= 'timeline-item align-items-start'>
                            <div class='timeline-label font-weight-bolder'>{$item->created_at->format('H:i')}</div>";
            }
            if ($item->type == 0) {
                $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-danger icon-xl\'></i>
                            </div>';
                $status = '<span class=\'label label-lg font-weight-bolder label-light-danger label-inline\'>逾期</span>';
                $line .= "<div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";
            } else {
                switch ($item->status) {
                    case 1: // 新建
                    case 2: // 待派小組長
                    case 3: // 待派工程師
                    case 4: // 待Callback
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-warning icon-xl\'></i>
                            </div>';
                        break;

                    case 5: // 待維修
                    case 6: // 待工程結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-success icon-xl\'></i>
                            </div>';
                        break;

                    case 7: // 待客戶驗收
                    case 8: // 待客服驗收
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-info icon-xl\'></i>
                            </div>';
                        break;

                    case 9: // 待結案
                    case 10: // 結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-primary icon-xl\'></i>
                            </div>';
                        break;

                    case 11: // 觀察後異常
                    case 12: // 業務不修結案
                        $line .= '<div class=\'timeline-badge\'>
                                <i class=\'fa fa-genderless text-danger icon-xl\'></i>
                            </div>';
                        break;
                }
                $status = ObstaclePresenter::status($item->status);
                $line .= "<div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";

                if (strpos($item->context, '換設備轉維修')) {
                    if ($item->alink) {
                        // 使用斜線（/）拆分 URL 字符串
                        $parts = explode('/', $item->alink);

                        // 獲取拆分後的數組的最後一個元素，即 ID 部分
                        $id   = end($parts);
                        $id   = Crypt::encryptString($id);
                        $link = '/repair/view/' . $id ?? '#';
                        $line .= "<a href='{$link}' class='text-primary font-weight-bolder' target='_blank'>{$item->aname}</a><br>";
                    }
                }
            }

            $context = $item->context;

            if (strpos($item->context, '續派工')) {
                $context = '續派工';
            }

            if (strpos($item->context, '改派工')) {
                $context = '改派工';
            }


            if (strpos($item->context, '取消派工')) {
                $context = '取消派工';
            }

            if (strpos($item->context, '請求改派')) {
                $context = '請求改派';
            }

            if (strpos($item->context, '增派工小組長')) {
                $context = '新增派工小組長';
            }

            if (strpos($item->context, '消派工小組長')) {
                $context = '取消派工小組長';
            }

            $line .= "<span class='text-muted'>{$context}</span>
                        </div>
                    </div>";
        }

        $line .= '</div>';

        return $line;
    }
}
