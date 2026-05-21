<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Models\EDM\Emails;
use App\Models\EDM\Member;
use App\Models\EDM\Mobiles;
use App\Models\EDM\Organization;
use App\Repositories\EDM\MemberRepository;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 會員管理控制器 (Member Management Controller)
 *
 * 負責處理 EDM 系統中會員的所有相關操作，包括清單查詢、詳細資料檢視、
 * 會員資料新增（支援批次導入邏輯）以及各項資料（狀態、Email、手機、業務）的修改。
 */
class MemberController extends Controller
{
    /**
     * 會員資料儲存庫
     */
    protected MemberRepository $memberRepository;

    /**
     * 使用者資訊服務
     */
    protected UserService $userService;

    /**
     * MemberController 建構子
     *
     * @param  MemberRepository  $memberRepository  注入會員相關資料處理邏輯
     * @param  UserService  $userService  注入使用者資訊處理服務
     */
    public function __construct(MemberRepository $memberRepository, UserService $userService)
    {
        $this->memberRepository = $memberRepository;
        $this->userService = $userService;
    }

    /**
     * 取得會員清單 (分頁)
     *
     * 根據請求參數過濾並回傳分頁後的會員列表。
     *
     * @param  Request  $request  包含 page (頁碼, 預設 1), pageSize (每頁筆數, 預設 20)
     * @return JsonResponse 包含分頁會員資料及總筆數
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
        $data = $this->memberRepository->GetList($params);
        $offset = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => [
                'items' => $pagedData,
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 取得特定會員的詳細資料
     *
     * 預載入會員關聯的銷售業務、Email、手機、所屬群組及其組織資訊。
     *
     * @param  Request  $request  包含 id (會員 ID)
     * @return JsonResponse 回傳完整的會員與關聯資料
     */
    public function view(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:member,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = Member::with(['emails', 'mobiles', 'groups', 'organizations', 'groups.members'])->find($request->input('id'));

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $member,
        ]);
    }

    /**
     * 批次新增或導入會員資料
     *
     * 具備去重邏輯，若「姓名 + 組織」已存在則僅更新/同步關聯。
     * 流程包括：處理組織資訊、比對/建立會員、同步 Email、同步行動電話、建立組織關聯與群組關聯。
     *
     * @param  Request  $request  包含 group_id (欲加入的群組ID) 與 data (多筆會員資料陣列)
     * @return JsonResponse 回傳處理完畢的會員物件列表與可能的提示資訊
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // group_id 0 為「不指派群組」的合法 sentinel，不做存在性檢查
            'group_id' => 'nullable|integer|min:0',
            'data' => 'required|array|min:1',
            'data.*.中文姓名' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $groupId = (int) $request->input('group_id');
        $data = $request->input('data');
        $creatorEmail = $this->userService->getUserFromHeader($request)['email'] ?? null;
        $results = [];
        foreach ($data as $item) {
            // 1. 處理組織 (Organization) - 優先處理以作為人員判定依據
            $organization = null;
            if (! empty($item['公司名稱'])) {
                $organization = Organization::firstOrCreate(
                    [
                        'name' => $item['公司名稱'],
                        'department' => $item['公司部門'] ?? null,
                    ],
                    [
                        'title' => $item['公司職稱'] ?? null,
                        'vat_no' => $item['公司統編'] ?? null,
                        'industry_type' => $item['公司行業別'] ?? null,
                        'country' => $item['公司所在國家'] ?? null,
                        'area' => $item['公司所在區域'] ?? null,
                        'address' => $item['公司地址'] ?? null,
                        'phone' => $item['公司電話'] ?? null,
                        'ext' => $item['公司分機'] ?? null,
                        'fax' => $item['公司傳真'] ?? null,
                    ]
                );
            }

            // 2. 處理 Member - 若「姓名 + 公司 + 部門」相同則不重複增加
            // 先嘗試尋找
            $member = Member::where('name', $item['中文姓名'])
                ->when($organization, function ($query) use ($organization) {
                    return $query->whereHas('organizations', function ($q) use ($organization) {
                        $q->where('organization.id', $organization->id);
                    });
                })
                ->when(! $organization, function ($query) {
                    return $query->whereDoesntHave('organizations');
                })
                ->first();

            // 若找不到則建立
            if (! $member) {
                $member = Member::create([
                    'name' => $item['中文姓名'],
                    'status' => $item['status'] ?? 1,
                    'sales_email' => $item['負責業務email'] ?? null,
                    'creator_email' => $creatorEmail,
                ]);
            }

            // 3. 處理 Email
            if (! empty($item['電子郵件'])) {
                $email = Emails::firstOrCreate(['email' => $item['電子郵件']]);
                $member->emails()->syncWithoutDetaching([$email->id]);
            }

            // 4. 處理手機 (Mobile)
            if (! empty($item['行動電話'])) {
                $mobile = Mobiles::firstOrCreate(['mobile' => $item['行動電話']]);
                $member->mobiles()->syncWithoutDetaching([$mobile->id]);
            }

            // 5. 處理組織關聯 (Organization Relation)
            if ($organization) {
                $member->organizations()->syncWithoutDetaching([$organization->id]);
            }

            // 6. 處理群組關聯 (Group)
            if ($groupId > 0) {
                $member->groups()->syncWithoutDetaching([$groupId]);
            }

            $results[] = $member->load(['emails', 'mobiles', 'organizations', 'groups']);
        }

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $results,
        ]);
    }

    /**
     * 更新會員的使用狀態
     *
     * @param  Request  $request  包含 member_id (會員 ID) 與 status (欲變更的狀態值)
     * @return JsonResponse
     */
    public function editStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:member,id',
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

        $member = Member::find($request->input('member_id'));
        $member->status = $request->input('status');
        $member->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $member,
        ]);
    }

    /**
     * 更新會員指定的電子郵件地址
     *
     * @param  Request  $request  包含 id (Email 資料 ID) 與 email (欲更新的信箱地址)
     * @return JsonResponse
     */
    public function editEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:email,id',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = Emails::find($request->input('id'));
        $member->email = $request->input('email');
        $member->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $member,
        ]);
    }

    /**
     * 更新會員指定的行動電話號碼
     *
     * @param  Request  $request  包含 id (Mobile 資料 ID) 與 mobile (欲更新的號碼)
     * @return JsonResponse
     */
    public function editMobile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:mobile,id',
            'mobile' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = Mobiles::find($request->input('id'));
        $member->mobile = $request->input('mobile');
        $member->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $member,
        ]);
    }

    /**
     * 重新指派會員的負責業務 email
     *
     * @param  Request  $request  包含 member_id (會員 ID) 與 sales_email (業務 email)
     * @return JsonResponse
     */
    public function editSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:member,id',
            'sales_email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 1,
                'status' => false,
                'message' => '欄位驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = Member::find($request->input('member_id'));
        $member->sales_email = $request->input('sales_email');
        $member->save();

        return response()->json([
            'code' => 0,
            'status' => true,
            'data' => $member,
        ]);
    }
}
