<?php

namespace Tests\Feature\EDM;

use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Models\EDM\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PreparesMeetingConnection;
use Tests\TestCase;

class GroupRouteTest extends TestCase
{
    use PreparesMeetingConnection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareMeetingConnection();
    }

    private function withUserHeader(array $user = ['enumber' => 'E001', 'realName' => '測試使用者']): array
    {
        return ['X-User-Info' => base64_encode(json_encode($user))];
    }

    public function test_list_returns_paginated_groups_and_filters_by_name(): void
    {
        Group::create(['name' => '行銷群組', 'status' => 1, 'creator_enumber' => 'E001']);
        Group::create(['name' => '行銷群組二', 'status' => 1, 'creator_enumber' => 'E001']);
        Group::create(['name' => '研發群組', 'status' => 1, 'creator_enumber' => 'E001']);

        $response = $this->postJson('/api/edm/group/list', [
            'groupName' => '行銷',
            'page' => 1,
            'pageSize' => 10,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true, 'data' => ['total' => 2]]);
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_view_returns_group_with_members(): void
    {
        $group = Group::create(['name' => '測試群組', 'status' => 1, 'creator_enumber' => 'E001']);
        $member = Member::create(['name' => 'Andy', 'status' => 1, 'sales' => '']);
        $member->groups()->attach($group->id);

        $response = $this->postJson('/api/edm/group/view', ['id' => $group->id]);

        $response->assertOk()->assertJson([
            'code' => 0,
            'status' => true,
            'data' => ['id' => $group->id, 'name' => '測試群組'],
        ]);
        $this->assertCount(1, $response->json('data.members'));
    }

    public function test_edit_status_updates_group(): void
    {
        $group = Group::create(['name' => 'G', 'status' => 1, 'creator_enumber' => 'E001']);

        $response = $this->postJson('/api/edm/group/editStatus', [
            'group_id' => $group->id,
            'status' => 0,
        ]);

        $response->assertOk();
        $this->assertSame(0, $group->fresh()->status);
    }

    public function test_create_persists_group_with_user_from_header(): void
    {
        $response = $this->postJson(
            '/api/edm/group/create',
            ['group_name' => '新群組', 'note' => '備註'],
            $this->withUserHeader(['enumber' => 'EHDR'])
        );

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertDatabaseHas('group', [
            'name' => '新群組',
            'note' => '備註',
            'creator_enumber' => 'EHDR',
            'status' => 0,
        ]);
    }

    public function test_get_event_list_returns_events_associated_with_group(): void
    {
        $group = Group::create(['name' => 'G', 'status' => 1, 'creator_enumber' => 'E001']);

        $event1 = Event::create([
            'event_number' => 'E260423001',
            'title' => '活動一',
            'content' => '',
            'img_url' => '',
            'start_time' => '2026-05-01 10:00:00',
            'end_time' => '2026-05-01 12:00:00',
            'address' => '',
            'type' => 1,
            'status' => 0,
            'creator_enumber' => 'E001',
        ]);
        $event2 = Event::create([
            'event_number' => 'E260423002',
            'title' => '活動二',
            'content' => '',
            'img_url' => '',
            'start_time' => '2026-05-02 10:00:00',
            'end_time' => '2026-05-02 12:00:00',
            'address' => '',
            'type' => 1,
            'status' => 0,
            'creator_enumber' => 'E001',
        ]);

        EventRelation::create(['event_id' => $event1->id, 'group_id' => $group->id, 'status' => 0]);
        EventRelation::create(['event_id' => $event1->id, 'group_id' => $group->id, 'status' => 0]);
        EventRelation::create(['event_id' => $event2->id, 'group_id' => $group->id, 'status' => 0]);

        $response = $this->postJson('/api/edm/group/getEventList', [
            'group_id' => $group->id,
        ]);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$event1->id, $event2->id], $ids);
    }
}
