<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Repositories\EDM\GroupRepository;
use Illuminate\Http\Request;

/**
 * 群組管理控制器
 *
 * 負責處理 EDM 系統中群組的清單查詢、詳細資料檢視、狀態修改以及新群組建立。
 */
class GroupController extends Controller
{
    protected GroupRepository $groupRepository;

    public function __construct(GroupRepository $groupRepository)
    {
        $this->groupRepository = $groupRepository;
    }

    /**
     * 取得群組清單（含分頁）
     *
     * @param Request $request 包含 page (頁碼) 與 pageSize (每頁筆數)
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $page      = (int) $request->input('page', 1);
        $pageSize  = (int) $request->input('pageSize', 20);
        $data      = $this->groupRepository->GetList($request->all());
        $offset    = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'items' => array_values($pagedData), // array_values 重新索引
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 檢視群組詳細資料
     *
     * @param Request $request 包含 id (群組 ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function view(Request $request)
    {
        $group = Group::with(['members'])->find($request->input('id'));

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $group,
        ]);
    }

    /**
     * 修改群組狀態
     *
     * @param Request $request 包含 group_id 與 status
     * @return \Illuminate\Http\JsonResponse
     */
    public function editStatus(Request $request)
    {
        $group         = Group::find($request->input('group_id'));
        $group->status = $request->input('status');
        $group->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $group,
        ]);
    }

    /**
     * 建立新群組
     *
     * @param Request $request 包含 name 與 status
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $group             = new Group();
        $group->name       = $request->input('group_name');
        $group->note       = $request->input('note');
        $group->creator_id = 8629;
        $group->status     = 0;
        $group->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $group,
        ]);
    }

    public function getEventList(Request $request)
    {
        $eventRelation = EventRelation::where('group_id', $request->input('group_id'))->distinct()->pluck('event_id')->toArray();
        $data          = Event::whereIn('id', $eventRelation)->get();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $data,
        ]);
    }
}
