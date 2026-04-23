<?php

namespace Tests\Feature\EDM;

use App\Models\EDM\Emails;
use App\Models\EDM\Group;
use App\Models\EDM\Member;
use App\Models\EDM\Mobiles;
use App\Models\EDM\Organization;
use App\Models\Meeting\MeetingUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PreparesMeetingConnection;
use Tests\TestCase;

class MemberRouteTest extends TestCase
{
    use PreparesMeetingConnection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareMeetingConnection();
    }

    public function test_list_returns_paginated_members(): void
    {
        Member::create(['name' => 'Alice', 'status' => 1, 'sales' => '']);
        Member::create(['name' => 'Alex', 'status' => 1, 'sales' => '']);
        Member::create(['name' => 'Bob', 'status' => 0, 'sales' => '']);

        $response = $this->postJson('/api/edm/member/list', [
            'name' => 'Al',
            'page' => 1,
            'pageSize' => 10,
        ]);

        $response->assertOk()
            ->assertJson([
                'code' => 0,
                'status' => true,
                'data' => ['total' => 2],
            ]);

        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_list_respects_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Member::create(['name' => 'M'.$i, 'status' => 1, 'sales' => '']);
        }

        $response = $this->postJson('/api/edm/member/list', [
            'page' => 2,
            'pageSize' => 2,
        ]);

        $response->assertOk();
        $this->assertSame(5, $response->json('data.total'));
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_view_returns_member_with_relations(): void
    {
        $member = Member::create(['name' => 'Carol', 'status' => 1, 'sales' => '']);

        $response = $this->postJson('/api/edm/member/view', ['id' => $member->id]);

        $response->assertOk()
            ->assertJson([
                'code' => 0,
                'status' => true,
                'data' => ['id' => $member->id, 'name' => 'Carol'],
            ]);
    }

    public function test_add_creates_member_and_links_relations(): void
    {
        $group = Group::create([
            'name' => '測試群組',
            'note' => '',
            'creator_enumber' => 'E001',
            'status' => 1,
        ]);
        MeetingUser::forceCreate(['enumber' => 'E001', 'name' => '測試業務']);

        $payload = [
            'group_id' => $group->id,
            'data' => [
                [
                    '中文姓名' => '小華',
                    '電子郵件' => 'hua@example.com',
                    '行動電話' => '0912345678',
                    '公司名稱' => '測試公司',
                    '公司部門' => '研發部',
                    '業務' => 'E001',
                ],
            ],
        ];

        $response = $this->postJson('/api/edm/member/add', $payload);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);

        $this->assertDatabaseHas('member', ['name' => '小華', 'sales' => 'E001']);
        $this->assertDatabaseHas('email', ['email' => 'hua@example.com']);
        $this->assertDatabaseHas('mobile', ['mobile' => '0912345678']);
        $this->assertDatabaseHas('organization', ['name' => '測試公司', 'department' => '研發部']);

        $member = Member::where('name', '小華')->first();
        $this->assertTrue($member->groups()->where('group.id', $group->id)->exists());
        $this->assertTrue($member->emails()->where('email', 'hua@example.com')->exists());
    }

    public function test_add_deduplicates_by_name_and_organization(): void
    {
        $org = Organization::create(['name' => '相同公司', 'department' => '業務部']);
        $existing = Member::create(['name' => '重複人', 'status' => 1, 'sales' => '']);
        $existing->organizations()->attach($org->id);

        $response = $this->postJson('/api/edm/member/add', [
            'group_id' => 0,
            'data' => [[
                '中文姓名' => '重複人',
                '公司名稱' => '相同公司',
                '公司部門' => '業務部',
            ]],
        ]);

        $response->assertOk();
        $this->assertSame(1, Member::where('name', '重複人')->count());
    }

    public function test_edit_status_updates_member(): void
    {
        $member = Member::create(['name' => 'Dan', 'status' => 0, 'sales' => '']);

        $response = $this->postJson('/api/edm/member/editStatus', [
            'member_id' => $member->id,
            'status' => 1,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertSame(1, $member->fresh()->status);
    }

    public function test_edit_email_updates_email_row(): void
    {
        $email = Emails::create(['email' => 'old@example.com']);

        $response = $this->postJson('/api/edm/member/editEmail', [
            'id' => $email->id,
            'email' => 'new@example.com',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('email', ['id' => $email->id, 'email' => 'new@example.com']);
    }

    public function test_edit_mobile_updates_mobile_row(): void
    {
        $mobile = Mobiles::create(['mobile' => '0900000000']);

        $response = $this->postJson('/api/edm/member/editMobile', [
            'id' => $mobile->id,
            'mobile' => '0911111111',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('mobile', ['id' => $mobile->id, 'mobile' => '0911111111']);
    }

    public function test_edit_sales_assigns_existing_sales(): void
    {
        MeetingUser::forceCreate(['enumber' => 'E999', 'name' => '新業務']);
        $member = Member::create(['name' => 'Eve', 'status' => 1, 'sales' => '']);

        $response = $this->postJson('/api/edm/member/editSales', [
            'member_id' => $member->id,
            'enumber' => 'E999',
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertSame('E999', $member->fresh()->sales);
    }

    public function test_edit_sales_returns_error_when_enumber_not_found(): void
    {
        $member = Member::create(['name' => 'Eve', 'status' => 1, 'sales' => '']);

        $response = $this->postJson('/api/edm/member/editSales', [
            'member_id' => $member->id,
            'enumber' => 'UNKNOWN',
        ]);

        $response->assertOk()->assertJson([
            'code' => 1,
            'status' => false,
            'msg' => '查無此業務',
        ]);
    }
}
