<?php

namespace App\Repositories\EDM;

use App\Models\EDM\Member;
use App\Repositories\RepositoryTrait;

class MemberRepository
{
    use RepositoryTrait;

    protected Member $model;

    public function __construct(Member $member)
    {
        $this->model = $member;
    }

    public function GetList(array $params)
    {
        return Member::query()
            ->with('emails')
            // 姓名模糊搜尋
            ->when(!empty($params['name']), function ($query) use ($params) {
                $query->where('name', 'like', '%' . $params['name'] . '%');
            })
            // 狀態精確比對
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
