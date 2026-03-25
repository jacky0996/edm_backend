<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use App\Libraries\CRMEntrustLib;
use App\Libraries\DataLib;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Models\EDM\Image;
use App\Repositories\EDM\EventRelationRepository;
use App\Repositories\EDM\EventRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    protected EventRepository $eventRepository;

    public function __construct(EventRepository $eventRepository, EventRelationRepository $eventRelationRepository)
    {
        $this->eventRepository         = $eventRepository;
        $this->eventRelationRepository = $eventRelationRepository;
        $this->data                    = $eventRelationRepository;
    }

    public function list(Request $request)
    {
        $page      = (int) $request->input('page', 1);
        $pageSize  = (int) $request->input('pageSize', 20);
        $data      = $this->eventRepository->GetList($request->all());
        $offset    = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'items' => array_values($pagedData),
                'total' => count($data),
            ],
        ]);
    }

    public function view(Request $request)
    {
        $event = Event::find($request->input('id'));

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $event,
        ]);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'summary'    => 'nullable|string',
            'content'    => 'nullable|string',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after_or_equal:start_time',
            'landmark'   => 'nullable|string|max:255',
            'address'    => 'nullable|string|max:255',
            'img_url'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '欄位驗證失敗',
                'errors'  => $validator->errors(),
            ], 422);
        }

        return $request->all();
        
        if ($request->input('is_registration') == 1) {
            $is_display = 1;
            if ($request->input('is_approval') == 1) {
                $is_approve = 1;
            } else {
                $is_approve = 1;
            }
        } else {
            $is_approve = 0;
            $is_display = 0;
        }

        try {
            $event               = new Event();
            $event->event_number = CRMEntrustLib::DocumentNumber('event');
            $event->title        = $request->input('title');
            $event->summary      = $request->input('summary');
            $event->content      = $request->input('content');
            $event->start_time   = $request->input('start_time');
            $event->end_time     = $request->input('end_time');
            $event->landmark     = $request->input('landmark');
            $event->address      = $request->input('address');
            $event->img_url      = $request->input('img_url');
            $event->type         = $request->input('type');
            $event->status       = 0;
            $event->is_approve   = $is_approve;
            $event->is_display   = $is_display;
            $event->is_qrcode    = 0;
            $event->creator      = $request->input('user_id');
            $event->save();

            return response()->json([
                'code'   => 0,
                'status' => true,
                'data'   => $event,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1,
                'status'  => false,
                'message' => '建立活動失敗',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $event             = Event::find($request->input('id'));
        $event->title      = $request->input('title');
        $event->summary    = $request->input('summary');
        $event->content    = $request->input('content');
        $event->start_time = $request->input('start_time');
        $event->end_time   = $request->input('end_time');
        $event->landmark   = $request->input('landmark');
        $event->address    = $request->input('address');
        $event->img_url    = $request->input('img_url');
        $event->save();

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $event,
        ]);
    }

    /**
     * 圖片上傳介面
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function imageUpload(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json(['code' => 1, 'status' => false, 'message' => 'No file uploaded'], 400);
        }

        $result = $this->eventRepository->uploadImage($request->all());
        if ($result) {
            $image = Image::create([
                'name' => $result['name'],
            ]);
        }

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $result,
        ]);
    }

    public function getImage(Request $request)
    {
        $images = Image::all();

        $data = $images->map(function ($image) {
            // 由於 Image 模型已新增 getPathAttribute()，此處可正常運作
            $image->url = DataLib::CheckFilePath($image);

            return $image;
        });

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => $data,
        ]);
    }

    public function getInviteList(Request $request)
    {
        $group     = Group::where('status', 1)->get()->toArray();
        $page      = (int) $request->input('page', 1);
        $pageSize  = (int) $request->input('pageSize', 20);
        $data      = $this->eventRelationRepository->inviteMembersList($request->all());
        $offset    = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $offset, $pageSize);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'data'   => [
                'group'  => $group,
                'member' => array_values($pagedData),
                'total'  => count($data),
            ],
        ]);
    }

    public function importGroup(Request $request)
    {
        // 預載入 members 及其手機與 Email 關聯
        $group = Group::with('members.mobiles', 'members.emails', 'members.organizations')->where('id', $request->input('group_id'))->first();

        if (!$group) {
            return response()->json([
                'code'   => 1,
                'status' => false,
            ]);
        }

        $members = $group->members;
        foreach ($members as $value) {
            $mobileId = $value->mobiles->first() ? $value->mobiles->first()->id : null;
            $emailId  = $value->emails->first() ? $value->emails->first()->id : null;
            $orgId    = $value->organizations->first() ? $value->organizations->first()->id : null;

            EventRelation::firstOrCreate(
                [
                    'event_id'  => $request->input('event_id'),
                    'member_id' => $value->id,
                    'group_id'  => $request->input('group_id'),
                ],
                [
                    'mobile_id'       => $mobileId,
                    'email_id'        => $emailId,
                    'organization_id' => $orgId,
                ]
            );
        }

        return response()->json([
            'code'   => 0,
            'status' => true,
        ]);
    }
}
