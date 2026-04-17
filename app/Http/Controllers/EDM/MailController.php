<?php

namespace App\Http\Controllers\EDM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Jobs\Common\SendAwsMailJob;

class MailController extends Controller
{
    /**
     * 發送邀請信件
     * 
     * @param Request $request 包含 event_id
     */
    public function inviteMail(Request $request)
    {
        $eventId = $request->input('event_id');
        $emails  = $request->input('emails'); // 接收陣列形式的收件者清單

        if (!$eventId || !is_array($emails) || empty($emails)) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '缺少必要參數 event_id 或收件者清單(emails)']);
        }

        // 1. 撈取活動資訊 (用於主旨與內文)
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到對應的活動']);
        }

        // 2. 準備寄信清單 (直接使用傳入的 emails 陣列)
        $mailChunk = [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mailChunk[] = [
                    'email'   => $email,
                    'subject' => $event->title,
                    'body'    => $event->content,
                ];
            }
        }

        if (empty($mailChunk)) {
            return response()->json(['code' => 1, 'status' => false, 'message' => '找不到有效的電子郵件地址']);
        }

        // 3. 呼叫 Job 進入隊列執行
        SendAwsMailJob::dispatch($mailChunk);

        return response()->json([
            'code'   => 0,
            'status' => true,
            'message' => '邀請信件已成功加入發送隊列',
            'count'  => count($mailChunk)
        ]);
    }
}
