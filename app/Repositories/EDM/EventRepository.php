<?php

namespace App\Repositories\EDM;

use App\Models\EDM\Event;
use App\Repositories\RepositoryTrait;

class EventRepository
{
    use RepositoryTrait;

    protected Event $model;

    public function __construct(Event $event)
    {
        $this->model = $event;
    }

    public function GetList(array $params)
    {
        return Event::query()
            // 建立者過濾：僅看自己建立的活動
            ->when(! empty($params['creator_email']), function ($query) use ($params) {
                $query->where('creator_email', $params['creator_email']);
            })
            // event 表的標題欄位為 title，相容前端帶 name / title 兩種命名
            ->when(! empty($params['title']) || ! empty($params['name']), function ($query) use ($params) {
                $keyword = $params['title'] ?? $params['name'];
                $query->where('title', 'like', '%'.$keyword.'%');
            })
            ->when(isset($params['status']) && in_array($params['status'], [0, 1, '0', '1'], true), function ($query) use ($params) {
                $query->where('status', $params['status']);
            })
            ->get()
            ->toArray();
    }

    /**
     * 處理圖片上傳
     *
     * @param  array  $params  包含 'file' (UploadedFile) 與 'type'
     */
    public function uploadImage(array $params): array
    {
        try {
            $file = $params['file'];
            $type = $params['type'] ?? 'default';

            $dir = ($type == 'ckeditor') ? 'edm/uat/ckeditor' : 'edm/uat';
            $path = $file->store($dir, 'sftp');

            return [
                'status' => true,
                'path' => $path,
                'name' => $path,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
