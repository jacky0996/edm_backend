<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Models\EDM\Emails;
use App\Models\EDM\Member;
use App\Models\EDM\Mobiles;
use App\Models\EDM\Organization;
use App\Models\User;
use App\Repositories\EDM\MemberRepository;
use Illuminate\Http\Request;

/**
 * 會員管理控制器
 *
 * 負責處理 EDM 系統中會員的清單查詢、詳細資料檢視、新增（含批次導入邏輯）以及狀態與資料修改。
 */
class MemberController extends Controller
{
    protected MemberRepository $memberRepository;

    public function __construct(MemberRepository $memberRepository)
    {
        $this->memberRepository = $memberRepository;
    }

    /**
     * 取得會員清單（含分頁）
     *
     * @param Request $request 包含 page (頁碼) 與 pageSize (每頁筆數)
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $page      = (int) $request->input('page', 1);
        $pageSize  = (int) $request->input('pageSize', 20);
        $data      = $this->memberRepository->GetList($request->all());
        $offset    = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'items' => $pagedData,
                'total' => count($data),
            ],
        ]);
    }

    /**
     * 檢視會員詳細資料
     *
     * @param Request $request 包含 id (會員 ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function view(Request $request)
    {
        $member = Member::with(['sales', 'emails', 'mobiles', 'groups', 'organizations', 'groups.members', 'groups.creator'])->find($request->input('id'));

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $member,
        ]);
    }

    /**
     * 新增會員資料（通常用於 Excel 匯入或手動批次新增）
     *
     * 邏輯包含：
     * 1. 處理會員基本資料（若姓名相同則不重複增加）
     * 2. 處理並關聯 Email
     * 3. 處理並關聯手機
     * 4. 處理並關聯組織/公司資料
     * 5. 處理群組關聯
     *
     * @param Request $request 包含 group_id 與 data (會員資料陣列)
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $groupId = (int) $request->input('group_id');
        $data    = $request->input('data');
        $results = [];
        $str     = '';
        foreach ($data as $item) {
            // 1. 處理組織 (Organization) - 優先處理以作為人員判定依據
            $organization = null;
            if (!empty($item['公司名稱'])) {
                $organization = Organization::firstOrCreate(
                    [
                        'name'       => $item['公司名稱'],
                        'department' => $item['公司部門'] ?? null,
                    ],
                    [
                        'title'         => $item['公司職稱']     ?? null,
                        'vat_no'        => $item['公司統編']     ?? null,
                        'industry_type' => $item['公司行業別']   ?? null,
                        'country'       => $item['公司所在國家'] ?? null,
                        'area'          => $item['公司所在區域'] ?? null,
                        'address'       => $item['公司地址']     ?? null,
                        'phone'         => $item['公司電話']     ?? null,
                        'ext'           => $item['公司分機']     ?? null,
                        'fax'           => $item['公司傳真']     ?? null,
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
                ->when(!$organization, function ($query) {
                    return $query->whereDoesntHave('organizations');
                })
                ->first();

            // 若找不到則建立
            if (!$member) {
                $sales = User::where('enumber', $item['業務'])
                    ->orWhere('old_enumber', $item['業務'])
                    ->first();
                if (!$sales && !empty($item['業務'])) {
                    $str .= $item['業務'] . '-查無此業務，故' . $item['中文姓名'] . '未指派業務';
                }
                $member = Member::create([
                    'name'        => $item['中文姓名'],
                    'national_id' => $item['national_id'] ?? '',
                    'status'      => $item['status']      ?? 1,
                    'sales_id'    => $sales->id           ?? null,
                ]);
            }

            // 3. 處理 Email
            if (!empty($item['電子郵件'])) {
                $email = Emails::firstOrCreate(['email' => $item['電子郵件']]);
                $member->emails()->syncWithoutDetaching([$email->id]);
            }

            // 4. 處理手機 (Mobile)
            if (!empty($item['行動電話'])) {
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
            'code'   => 0,
            'status' => true,
            'data'   => $results,
            'msg'    => empty($str) ? $str : null,
        ]);
    }

    /**
     * 修改會員狀態
     *
     * @param Request $request 包含 member_id 與 status
     * @return \Illuminate\Http\JsonResponse
     */
    public function editStatus(Request $request)
    {
        $member         = Member::find($request->input('member_id'));
        $member->status = $request->input('status');
        $member->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $member,
        ]);
    }

    /**
     * 修改會員電子郵件
     *
     * @param Request $request 包含 id (Email ID) 與 email
     * @return \Illuminate\Http\JsonResponse
     */
    public function editEmail(Request $request)
    {
        $member        = Emails::find($request->input('id'));
        $member->email = $request->input('email');
        $member->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $member,
        ]);
    }

    /**
     * 修改會員行動電話
     *
     * @param Request $request 包含 id (Mobile ID) 與 mobile
     * @return \Illuminate\Http\JsonResponse
     */
    public function editMobile(Request $request)
    {
        $member         = Mobiles::find($request->input('id'));
        $member->mobile = $request->input('mobile');
        $member->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $member,
        ]);
    }

    public function editSales(Request $request)
    {
        $member = Member::find($request->input('member_id'));
        $user   = User::where('enumber', $request->input('enumber'))->orWhere('old_enumber', $request->input('enumber'))->first();
        if (!$user) {
            return response()->json([
                'code'   => 1,
                'status' => false,
                'data'   => null,
                'msg'    => '查無此業務',
            ]);
        }
        $member->sales_id = $user->id;
        $member->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $member,
        ]);
    }
}
