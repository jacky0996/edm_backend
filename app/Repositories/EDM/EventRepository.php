<?php

namespace App\Repositories\EDM;

use App\Models\EDM\Event;
use App\Repositories\RepositoryTrait;
use Illuminate\Http\UploadedFile;

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
            ->when(!empty($params['name']), function ($query) use ($params) {
                $query->where('name', 'like', '%' . $params['name'] . '%');
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
     * @param array $params 包含 'file' (UploadedFile) 與 'type'
     * @return array
     */
    public function uploadImage(array $params): array
    {
        try {
            $file = $params['file'];
            $type = $params['type'] ?? 'default';

            $dir  = ($type == 'ckeditor') ? 'edm/uat/ckeditor' : 'edm/uat';
            $path = $file->store($dir, 'sftp');

            return [
                'status' => true,
                'path'   => $path,
                'name'   => $path,
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
