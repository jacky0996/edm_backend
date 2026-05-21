<?php

namespace App\Repositories\EDM;

use App\Models\EDM\Group;
use App\Repositories\RepositoryTrait;

class GroupRepository
{
    use RepositoryTrait;

    protected Group $model;

    public function __construct(Group $group)
    {
        $this->model = $group;
    }

    public function GetList(array $params)
    {
        return Group::query()
            ->with(['members'])
            // 建立者過濾：僅看自己建立的群組
            ->when(! empty($params['creator_email']), function ($query) use ($params) {
                $query->where('creator_email', $params['creator_email']);
            })
            ->when(! empty($params['groupName']) || ! empty($params['name']), function ($query) use ($params) {
                $keyword = $params['groupName'] ?? $params['name'];
                $query->where('name', 'like', '%'.$keyword.'%');
            })
            // 狀態精確比對 (群組狀態可能是 0-未啟用, 1-啟用 等)
            ->when(isset($params['status']) && in_array($params['status'], [0, 1, '0', '1'], true), function ($query) use ($params) {
                $query->where('status', $params['status']);
            })
            ->get()
            ->toArray();
    }

    public function RoleGetList($datas, $user = null) {}

    public function RoleSelect($user, $datas) {}
}
