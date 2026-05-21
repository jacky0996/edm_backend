<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Libraries\DataLib;
use App\Libraries\EntrustLib;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Models\EDM\Image;
use App\Repositories\EDM\EventRelationRepository;
use App\Repositories\EDM\EventRepository;
use App\Services\GoogleFormService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 活動管理控制器 (Event Management Controller)
 *
 * 負責處理 EDM 系統中所有的活動流程，包括活動的增刪改查 (CRUD)、
 * 圖片上傳管理、邀請名單導入，以及 Google 表單綁定的入口 (實際邏輯委派至 GoogleFormService)。
 */
class EventController extends Controller
{
    /**
     * 建構子：注入相依服務與儲存庫
     *
     * @param  EventRepository  $eventRepository  活動資料儲存庫
     * @param  EventRelationRepository  $eventRelationRepository  活動關聯(群組)儲存庫
     * @param  UserService  $userService  使用者權限與資訊相關服務
     * @param  GoogleFormService  $googleFormService  Google 問卷業務服務
     */
    public function __construct(
        protected EventRepository $eventRepository,
        protected EventRelationRepository $eventRelationRepository,
        protected UserService $userService,
        protected GoogleFormService $googleFormService,
    ) {}

    /**
     * 取得活動列表 (分頁)
     */
    public function list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 20);
        $user = $this->userService->getUserFromHeader($request);
        $params = $request->all();
        if (! empty($user['email'])) {
            $params['creator_email'] = $user['email'];
        }
        $data = $this->eventRepository->GetList($params);
        $offset = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => [
                'items' => array_values($pagedData),
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 取得特定活動的詳細資訊
     */
    public function view(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:event,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = Event::find($request->input('id'));

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $event,
        ]);
    }

    /**
     * 建立新活動
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'landmark' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->input('is_registration') == 1) {
            $is_display = 1;
            $is_approve = ($request->input('is_approval') == 1) ? 1 : 1;
        } else {
            $is_approve = 0;
            $is_display = 0;
        }

        try {
            $user = $this->userService->getUserFromHeader($request);
            $event = new Event;
            $event->event_number = EntrustLib::DocumentNumber('event');
            $event->title = $request->input('title');
            $event->summary = $request->input('summary');
            $event->content = $request->input('content');
            $event->start_time = $request->input('start_time');
            $event->end_time = $request->input('end_time');
            $event->landmark = $request->input('landmark');
            $event->address = $request->input('address');
            $event->type = $request->input('type');
            $event->status = 0;
            $event->is_approve = $is_approve;
            $event->is_display = $is_display;
            $event->is_qrcode = 0;
            $event->creator_email = $user['email'];
            $event->save();

            return response()->json([
                'code' => 0,
                'status' => true,
                'data' => $event,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '建立活動失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新現有活動資訊
     *
     * 若切換為不需要審核，系統會透過 GoogleFormService 自動核准所有待審紀錄。
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:event,id',
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after_or_equal:start_time',
            'landmark' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->input('is_registration') == 1) {
            $is_display = 1;
            $is_approve = ($request->input('is_approve') == 1) ? 1 : 0;
        } else {
            $is_approve = 0;
            $is_display = 0;
        }

        $event = Event::find($request->input('id'));

        // 檢查審核設定是否從「需審核」變更為「不需審核」
        $shouldAutoApproveExisting = ($event->is_approve == 1 && $is_approve == 0);

        $event->title = $request->input('title');
        $event->summary = $request->input('summary');
        $event->content = $request->input('content');
        $event->start_time = $request->input('start_time');
        $event->end_time = $request->input('end_time');
        $event->landmark = $request->input('landmark');
        $event->address = $request->input('address');
        $event->type = $request->input('type');
        $event->status = 0;
        $event->is_approve = $is_approve;
        $event->is_display = $is_display;
        $event->is_qrcode = 0;
        $event->save();

        // 處理由「需審核」切換為「不需審核」的連動：將所有待審中的紀錄設為通過
        if ($shouldAutoApproveExisting) {
            $this->googleFormService->approvePendingByEventId($event->id);
        }

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $event,
        ]);
    }

    /**
     * 上傳活動相關圖片
     */
    public function imageUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->eventRepository->uploadImage($request->all());
        if ($result && $result['status']) {
            Image::create([
                'name' => $result['name'],
            ]);
        }

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $result,
        ]);
    }

    /**
     * 取得系統中所有上傳圖片的存取網址
     */
    public function getImage(Request $request)
    {
        $images = Image::all();

        $data = $images->map(function ($image) {
            $image->url = DataLib::CheckFilePath($image);

            return $image;
        });

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $data,
        ]);
    }

    /**
     * 取得特定活動的獲邀名單
     */
    public function getInviteList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
            'page' => 'nullable|integer|min:1',
            'pageSize' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $group = Group::where('status', 1)->get()->toArray();
        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 20);
        $data = $this->eventRelationRepository->inviteMembersList($request->all());
        $offset = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => [
                'group' => $group,
                'member' => array_values($pagedData),
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 批次匯入群組成員至活動邀請名單
     */
    public function importGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
            'group_id' => 'required|integer|exists:group,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $group = Group::with('members.mobiles', 'members.emails', 'members.organizations')->where('id', $request->input('group_id'))->first();

        if (! $group) {
            return response()->json(['code' => 1, 'status' => false]);
        }

        $members = $group->members;
        foreach ($members as $value) {
            $mobileId = $value->mobiles->first() ? $value->mobiles->first()->id : null;
            $emailId = $value->emails->first() ? $value->emails->first()->id : null;
            $orgId = $value->organizations->first() ? $value->organizations->first()->id : null;

            EventRelation::firstOrCreate(
                [
                    'event_id' => $request->input('event_id'),
                    'member_id' => $value->id,
                    'group_id' => $request->input('group_id'),
                ],
                [
                    'mobile_id' => $mobileId,
                    'email_id' => $emailId,
                    'organization_id' => $orgId,
                ]
            );
        }

        return response()->json(['code' => 0, 'status' => true]);
    }

    /**
     * 取得該活動的表單顯示狀態與所有相關統計/回覆內容
     */
    public function getDisplayList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = $this->eventRelationRepository->getFormDisplay($request->input('event_id'));

        if (! $event) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到對應的活動']);
        }

        if ($event->is_display == 0) {
            return response()->json([
                'code' => 0,
                'status' => true,
                'message' => '活動不需填寫報名表',
                'data' => [
                    'event' => $event,
                    'requires_registration' => false,
                ],
            ]);
        }

        if (! $event->googleForm) {
            return response()->json([
                'code' => 0,
                'status' => true,
                'message' => '活動需要報名表，但尚未綁定 Google 表單',
                'data' => [
                    'event' => $event,
                    'requires_registration' => true,
                    'google_form_bound' => false,
                ],
            ]);
        }

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => [
                'event' => $event,
                'requires_registration' => true,
                'google_form_bound' => true,
                'form_details' => $event->googleForm,
            ],
        ]);
    }

    /**
     * 更新活動顯示狀態
     */
    public function updateDisplay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
            'is_display' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = Event::find($request->input('event_id'));
        $event->is_display = $request->input('is_display');
        $event->save();

        return response()->json(['code' => 0, 'status' => true, 'message' => '顯示狀態更新成功']);
    }

    /**
     * 向 Google API 請求建立全新的表單並綁定至活動
     */
    public function createGoogleForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = Event::find($request->input('event_id'));

        try {
            $result = $this->googleFormService->createFormForEvent($event, $request->all());

            return response()->json([
                'code' => $result['status'] ? 0 : 1,
                'status' => $result['status'],
                'message' => $result['message'] ?? null,
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '表單建立流程發生錯誤',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 取得已綁定的 Google 表單當前結構資訊 (與 Google API 即時同步)
     */
    public function getGoogleForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->googleFormService->getFormWithGoogleDetails($request->input('id'));

            return response()->json([
                'code' => $result['status'] ? 0 : 1,
                'status' => $result['status'],
                'message' => $result['message'] ?? null,
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '取得表單詳情失敗：'.$e->getMessage(),
            ]);
        }
    }

    /**
     * 更新已綁定的 Google 表單架構 (全量同步)
     */
    public function updateGoogleForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'event_id' => 'nullable|integer|exists:event,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->googleFormService->syncForm(
                $request->input('id'),
                $request->input('event_id'),
                $request->all()
            );

            return response()->json([
                'code' => $result['status'] ? 0 : 1,
                'status' => $result['status'],
                'message' => $result['message'] ?? null,
                'data' => $result['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '更新流程失敗：'.$e->getMessage(),
            ]);
        }
    }

    /**
     * 刪除特定 Google 表單的綁定紀錄 (解除綁定)
     */
    public function delGoogleForm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->googleFormService->deleteForm($request->input('id'));

        return response()->json([
            'code' => $result['status'] ? 0 : 1,
            'status' => $result['status'],
            'message' => $result['message'] ?? null,
        ]);
    }

    /**
     * 取得活動的問卷填寫統計
     *
     * 用於活動數據儀表板。把 Google 問卷的填寫者 email 與 event_relation 內的邀請者 email 比對，
     * 區分邀請名單內已填寫 / 未填寫 / 邀請名單外填寫者三類。
     */
    public function getSurveyStats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|integer|exists:event,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $this->googleFormService->getSurveyStatsByEventId($request->input('event_id'));

            return response()->json([
                'code' => 0,
                'status' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '取得問卷統計失敗',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新報名紀錄的審核狀態
     */
    public function updateResponseStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'response_id' => 'required',
            'status' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->googleFormService->updateResponseStatus(
            $request->input('response_id'),
            $request->input('status')
        );

        return response()->json([
            'code' => $result['status'] ? 0 : 1,
            'status' => $result['status'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ]);
    }
}
