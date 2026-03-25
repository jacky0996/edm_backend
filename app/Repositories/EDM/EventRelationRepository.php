<?php

namespace App\Repositories\EDM;

use App\Models\EDM\EventRelation;
use App\Repositories\Repository;
use App\Repositories\RepositoryTrait;
use Illuminate\Support\Facades\Log;

class EventRelationRepository extends Repository
{
    use RepositoryTrait;

    public function model(): string
    {
        return EventRelation::class;
    }

    /**
     * 取得邀請人列表
     *
     * @param $dictionary
     * $dictionary = [
     *   'event_id'    => 活動id,
     *   'search'      => 進階搜尋,
     *   'type'        => 1:已刪除名單 all:全部名單,
     * ];
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function inviteMembersList($dictionary)
    {
        try {
            $datas    = EventRelation::query();
            $event_id = $dictionary['event_id'];
            $search   = $dictionary['search'] ?? null;
            $type     = $dictionary['type']   ?? null;
            $datas    = EventRelation::with('member', 'mobile', 'email')->where('event_id', $event_id)->get()->toArray();

            return $datas;
        } catch (\Exception $e) {
            Log::error('inviteMembersList Error: ' . $e->getMessage());

            return false;
        }
    }
}
