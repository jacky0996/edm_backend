<?php

namespace App\Libraries;

use App\Models\CRM\Obstacle\ObsTimeLine;
use App\Models\Project\Project;
use App\Models\Project\ProjectHeader;
use App\Models\Project\ProjectTimeLine;
use App\Presenters\CRM\ObstaclePresenter;
use App\Repositories\Project\ProjectHeaderRepository;

class TimeLineLib
{
    //TODO:TimeLine API
    /**
     * 塞入一筆timeline紀錄
     *
     * @param $data  = [
     *    type            類型:0=逾期，1=正常，2=事件
     *    header_id       Case Header ID
     *    project_id      Case ID
     *    user_id         CREATOR ID
     *    color           label顏色:1=primary，2=success，3=warning，4=danger，5=info，6=dark
     *    title           標題
     *    context         內容
     *    aname           超連結名稱
     *    alink           連結
     *    current_status  時間當下專案狀態
     * ]
     */
    public function pushLine($data): bool
    {
        $line = new ProjectTimeLine();
        $data = static::toTimelineData($data);
        $line->fill($data);

        if ($line->save()) {
            return true;
        }

        return false;
    }

    /**
     * 取得 timeline 紀錄 data
     * @param array $data
     * @return array
     */
    public static function toTimelineData(array $data): array
    {
        return [
            'type'           => $data['type']           ?? null,
            'header_id'      => $data['header_id']      ?? null,
            'project_id'     => $data['project_id']     ?? null,
            'user_id'        => $data['user_id']        ?? null,
            'color'          => $data['color']          ?? null,
            'title'          => $data['title']          ?? null,
            'context'        => $data['context']        ?? null,
            'aname'          => $data['aname']          ?? null,
            'alink'          => $data['alink']          ?? null,
            'current_status' => $data['current_status'] ?? null,
        ];
    }

    /**
     * TimeLine顯示
     *
     * @param $project  //傳project-header進來的話會抓取全部的header_id接在一起
     * @return string
     */
    public static function showLine(ProjectHeader|Project $project): string
    {
        $query = ProjectTimeLine::query();
        if ($project instanceof ProjectHeader) {
            $query->where('header_id', $project->id)->whereNull('project_id');
        } else {
            $query->where('project_id', $project->id)->where('header_id', $project->header_id);
        }

        $items = $query->orderByRaw('created_at desc, id desc')->get();

        $line = '<div class=\'timeline timeline-6 mt-3\'>';
        $date = null;

        foreach ($items as $item) {
            if ($date != $item->created_at->format('Y-m-d')) {
                $date = $item->created_at->format('Y-m-d');
                $line .= "<div class= 'timeline-item align-items-start p-0'>
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
                $color = 'dark';
                //沒有指定顏色，抓專案當前狀態
                if ($item->color) {
                    switch ($item->color) {
                        case 1:
                            $color = 'label-light-primary';
                            break;

                        case 2:
                            $color = 'label-light-success';
                            break;

                        case 3:
                            $color = 'label-light-warning';
                            break;

                        case 4:
                            $color = 'label-light-danger';
                            break;

                        case 5:
                            $color = 'label-light-info';
                            break;

                        case 6:
                            $color = 'label-light-dark';
                            break;
                    }
                } else {
                    $color = ProjectHeaderRepository::getStatusList()[$item->current_status]['class_name'];
                }

                $link = $item->alink ?? '#';

                //沒有指定標題，抓專案當前狀態
                if ($item->title) {
                    $status = "<span class='label label-lg font-weight-bolder {$color} label-inline h-auto p-2'- >{$item->title}</span>";
                } else {
                    $status = self::getStatusSpan($item->current_status);
                }

                $line .= "<div class='timeline-badge'>
                                <i class='fa fa-genderless text-{$color} icon-xl'></i>
                            </div><div class='timeline-content font-weight-bolder font-size-lg text-dark-75 pl-2'>
                            {$status}<br>";

                // TODO: BPM 尚未支援：直接跳轉到特定 BPM 單號的 Link
                if ($item->alink && $item->alink) {
                    if (!str_starts_with($item->alink ?? '', config('cross_system.bpm.api.url') ?? '')) {
                        $line .= "<a href='{$link}' class='text-primary font-weight-bolder' target='_blank'>{$item->aname}</a><br>";
                    }
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
     * Case 狀態span
     *
     * @param $data  (project header Object)
     * @return String
     */
    public static function getStatusSpan($status): string
    {
        $res                     = '';
        $ProjectHeaderRepository = new ProjectHeaderRepository();
        $statusList              = $ProjectHeaderRepository->getStatusList();
        $text                    = $statusList[$status]['name'];
        $class_name              = $statusList[$status]['class_name'];
        $res                     = "<span class='label label-lg font-weight-bolder {$class_name} label-inline'>{$text}</span>";

        return $res;
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
}
