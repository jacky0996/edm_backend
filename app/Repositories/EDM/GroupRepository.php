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
            ->with(['members', 'creator']) // 補回 Controller 原本撈取的關聯資料
            ->when(!empty($params['groupName']), function ($query) use ($params) {
                // 群組名稱模糊搜尋
                $query->where('name', 'like', '%' . $params['groupName'] . '%');
            })
            // 狀態精確比對 (群組狀態可能是 0-未啟用, 1-啟用 等)
            ->when(isset($params['status']) && in_array($params['status'], [0, 1, '0', '1'], true), function ($query) use ($params) {
                $query->where('status', $params['status']);
            })
            ->get()
            ->toArray();
    }

    public function RoleGetList($datas, $user = null)
    {
    }

    public function RoleSelect($user, $datas)
    {
    }
}
