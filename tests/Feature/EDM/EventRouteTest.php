<?php

namespace Tests\Feature\EDM;

use App\Models\DocumentCount;
use App\Models\EDM\Emails;
use App\Models\EDM\Event;
use App\Models\EDM\EventRelation;
use App\Models\EDM\Group;
use App\Models\EDM\Image;
use App\Models\EDM\Member;
use App\Models\EDM\Mobiles;
use App\Models\EDM\Organization;
use App\Models\Google\GoogleForm;
use App\Repositories\EDM\EventRelationRepository;
use App\Repositories\EDM\EventRepository;
use App\Services\GoogleFormService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\PreparesMeetingConnection;
use Tests\TestCase;

class EventRouteTest extends TestCase
{
    use PreparesMeetingConnection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareMeetingConnection();
        DocumentCount::firstOrCreate(['id' => 1], ['event' => 0]);

        // `image` 沒有官方 migration，測試中補上最小結構
        if (! Schema::hasTable('image')) {
            Schema::create('image', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function withUserHeader(array $user = ['enumber' => 'E001']): array
    {
        return ['X-User-Info' => base64_encode(json_encode($user))];
    }

    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'event_number' => 'E260423000',
            'title' => '活動',
            'summary' => '摘要',
            'content' => '內容',
            'img_url' => '',
            'start_time' => '2026-05-01 10:00:00',
            'end_time' => '2026-05-01 12:00:00',
            'landmark' => '',
            'address' => '',
            'type' => 1,
            'status' => 0,
            'creator_enumber' => 'E001',
            'is_approve' => 0,
            'is_display' => 0,
            'is_qrcode' => 0,
        ], $overrides));
    }

    public function test_list_paginates_events(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeEvent(['event_number' => 'E'.$i, 'title' => 'T'.$i]);
        }

        $response = $this->postJson('/api/edm/event/list', ['page' => 1, 'pageSize' => 2]);

        $response->assertOk()->assertJson(['data' => ['total' => 3]]);
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_view_returns_event_by_id(): void
    {
        $event = $this->makeEvent();

        $response = $this->postJson('/api/edm/event/view', ['id' => $event->id]);

        $response->assertOk()->assertJson(['data' => ['id' => $event->id, 'title' => '活動']]);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->postJson(
            '/api/edm/event/create',
            ['title' => ''],
            $this->withUserHeader()
        );

        $response->assertStatus(422)->assertJson([
            'code' => 1,
            'status' => false,
            'message' => '欄位驗證失敗',
        ]);
    }

    public function test_create_persists_event(): void
    {
        $response = $this->postJson('/api/edm/event/create', [
            'title' => '新活動',
            'summary' => '摘要',
            'content' => '內容',
            'img_url' => 'path/to/cover.png',
            'start_time' => '2026-05-10 10:00:00',
            'end_time' => '2026-05-10 12:00:00',
            'address' => '台北',
            'landmark' => '101',
            'type' => 1,
            'is_registration' => 1,
            'is_approval' => 1,
        ], $this->withUserHeader(['enumber' => 'EABC']));

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertDatabaseHas('event', [
            'title' => '新活動',
            'creator_enumber' => 'EABC',
            'is_approve' => 1,
            'is_display' => 1,
        ]);
    }

    public function test_update_rewrites_event_fields(): void
    {
        $event = $this->makeEvent(['img_url' => 'old/cover.png', 'content' => '舊內容', 'address' => '舊地址']);

        $response = $this->postJson('/api/edm/event/update', [
            'id' => $event->id,
            'title' => '更新後',
            'content' => '新內容',
            'img_url' => 'new/cover.png',
            'start_time' => '2026-05-10 10:00:00',
            'end_time' => '2026-05-10 12:00:00',
            'address' => '台中',
            'landmark' => '101',
            'type' => 2,
            'is_registration' => 1,
            'is_approve' => 1,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('event', ['id' => $event->id, 'title' => '更新後', 'is_approve' => 1]);
    }

    public function test_update_triggers_auto_approve_when_switching_off_approval(): void
    {
        $event = $this->makeEvent([
            'is_approve' => 1,
            'img_url' => 'cover.png',
            'content' => '內容',
            'address' => '台北',
        ]);

        $mock = Mockery::mock(GoogleFormService::class);
        $mock->shouldReceive('approvePendingByEventId')->once()->with($event->id)->andReturn(2);
        $this->app->instance(GoogleFormService::class, $mock);

        $response = $this->postJson('/api/edm/event/update', [
            'id' => $event->id,
            'title' => '活動',
            'content' => '內容',
            'img_url' => 'cover.png',
            'start_time' => '2026-05-10 10:00:00',
            'end_time' => '2026-05-10 12:00:00',
            'address' => '台北',
            'landmark' => '101',
            'type' => 1,
            'is_registration' => 1,
            'is_approve' => 0,
        ]);

        $response->assertOk();
    }

    public function test_image_upload_returns_error_without_file(): void
    {
        $response = $this->postJson('/api/edm/event/imageUpload', []);

        $response->assertStatus(400)->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_image_upload_saves_image_record(): void
    {
        Storage::fake('sftp');

        $repoMock = Mockery::mock(EventRepository::class);
        $repoMock->shouldReceive('uploadImage')->once()->andReturn([
            'status' => true,
            'path' => 'edm/uat/file.png',
            'name' => 'edm/uat/file.png',
        ]);
        $this->app->instance(EventRepository::class, $repoMock);

        $response = $this->post('/api/edm/event/imageUpload', [
            'file' => UploadedFile::fake()->image('file.png'),
            'type' => 'default',
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertDatabaseHas('image', ['name' => 'edm/uat/file.png']);
    }

    public function test_get_image_returns_list(): void
    {
        Image::create(['name' => 'edm/uat/x.png']);

        $response = $this->postJson('/api/edm/event/getImage', []);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertIsArray($response->json('data'));
    }

    public function test_get_invite_list_uses_repository(): void
    {
        $event = $this->makeEvent();
        $group = Group::create(['name' => 'G', 'status' => 1, 'creator_enumber' => 'E001']);
        $member = Member::create(['name' => 'Mem', 'status' => 1, 'sales' => '']);
        EventRelation::create([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'group_id' => $group->id,
            'status' => 0,
        ]);

        $repoMock = Mockery::mock(EventRelationRepository::class);
        $repoMock->shouldReceive('inviteMembersList')->andReturn([
            ['id' => 1, 'member_id' => $member->id],
        ]);
        $this->app->instance(EventRelationRepository::class, $repoMock);

        $response = $this->postJson('/api/edm/event/getInviteList', [
            'event_id' => $event->id,
            'page' => 1,
            'pageSize' => 20,
        ]);

        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'data' => ['group', 'member', 'total'],
        ]);
        $this->assertSame(1, $response->json('data.total'));
    }

    public function test_import_group_returns_error_when_group_missing(): void
    {
        $response = $this->postJson('/api/edm/event/importGroup', [
            'group_id' => 9999,
            'event_id' => 1,
        ]);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_import_group_creates_event_relations(): void
    {
        $event = $this->makeEvent();
        $group = Group::create(['name' => 'G', 'status' => 1, 'creator_enumber' => 'E001']);
        $member = Member::create(['name' => 'M1', 'status' => 1, 'sales' => '']);
        $email = Emails::create(['email' => 'm1@example.com']);
        $mobile = Mobiles::create(['mobile' => '0900000001']);
        $org = Organization::create(['name' => 'Org']);
        $member->emails()->attach($email->id);
        $member->mobiles()->attach($mobile->id);
        $member->organizations()->attach($org->id);
        $member->groups()->attach($group->id);

        $response = $this->postJson('/api/edm/event/importGroup', [
            'group_id' => $group->id,
            'event_id' => $event->id,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertDatabaseHas('event_relation', [
            'event_id' => $event->id,
            'member_id' => $member->id,
            'group_id' => $group->id,
            'mobile_id' => $mobile->id,
            'email_id' => $email->id,
            'organization_id' => $org->id,
        ]);
    }

    public function test_get_display_list_requires_event_id(): void
    {
        $response = $this->postJson('/api/edm/event/getDisplayList', []);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_get_display_list_returns_no_registration_when_display_disabled(): void
    {
        $event = $this->makeEvent(['is_display' => 0]);

        $response = $this->postJson('/api/edm/event/getDisplayList', [
            'event_id' => $event->id,
        ]);

        $response->assertOk()->assertJson([
            'code' => 0,
            'status' => true,
            'data' => ['requires_registration' => false],
        ]);
    }

    public function test_get_display_list_indicates_unbound_google_form(): void
    {
        $event = $this->makeEvent(['is_display' => 1]);

        $response = $this->postJson('/api/edm/event/getDisplayList', [
            'event_id' => $event->id,
        ]);

        $response->assertOk()->assertJson([
            'code' => 0,
            'status' => true,
            'data' => ['requires_registration' => true, 'google_form_bound' => false],
        ]);
    }

    public function test_get_display_list_returns_form_details_when_bound(): void
    {
        $event = $this->makeEvent(['is_display' => 1]);
        GoogleForm::create([
            'event_id' => $event->id,
            'form_id' => 'FORM1',
            'form_url' => 'https://forms.google.com/FORM1',
            'type' => 'google_form',
        ]);

        $response = $this->postJson('/api/edm/event/getDisplayList', [
            'event_id' => $event->id,
        ]);

        $response->assertOk()->assertJson([
            'data' => ['google_form_bound' => true],
        ]);
    }

    public function test_update_display_returns_error_for_missing_event(): void
    {
        $response = $this->postJson('/api/edm/event/updateDisplay', [
            'event_id' => 9999,
            'is_display' => 1,
        ]);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_update_display_updates_flag(): void
    {
        $event = $this->makeEvent(['is_display' => 0]);

        $response = $this->postJson('/api/edm/event/updateDisplay', [
            'event_id' => $event->id,
            'is_display' => 1,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
        $this->assertSame(1, $event->fresh()->is_display);
    }

    public function test_create_google_form_event_not_found(): void
    {
        $response = $this->postJson('/api/edm/event/createGoogleForm', [
            'event_id' => 9999,
        ]);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_create_google_form_delegates_to_service(): void
    {
        $event = $this->makeEvent();

        $serviceMock = Mockery::mock(GoogleFormService::class);
        $serviceMock->shouldReceive('createFormForEvent')
            ->once()
            ->andReturn(['status' => true, 'message' => 'ok', 'data' => ['id' => 1]]);
        $this->app->instance(GoogleFormService::class, $serviceMock);

        $response = $this->postJson('/api/edm/event/createGoogleForm', [
            'event_id' => $event->id,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true, 'message' => 'ok']);
    }

    public function test_get_google_form_requires_id(): void
    {
        $response = $this->postJson('/api/edm/event/getGoogleForm', []);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_get_google_form_returns_service_result(): void
    {
        $serviceMock = Mockery::mock(GoogleFormService::class);
        $serviceMock->shouldReceive('getFormWithGoogleDetails')
            ->with(5)
            ->andReturn(['status' => true, 'data' => ['record' => []]]);
        $this->app->instance(GoogleFormService::class, $serviceMock);

        $response = $this->postJson('/api/edm/event/getGoogleForm', ['id' => 5]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
    }

    public function test_update_google_form_delegates_to_service(): void
    {
        $serviceMock = Mockery::mock(GoogleFormService::class);
        $serviceMock->shouldReceive('syncForm')
            ->andReturn(['status' => true, 'message' => 'updated']);
        $this->app->instance(GoogleFormService::class, $serviceMock);

        $response = $this->postJson('/api/edm/event/updateGoogleForm', [
            'id' => 1,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
    }

    public function test_del_google_form_delegates_to_service(): void
    {
        $serviceMock = Mockery::mock(GoogleFormService::class);
        $serviceMock->shouldReceive('deleteForm')
            ->with(1)
            ->andReturn(['status' => true, 'message' => 'done']);
        $this->app->instance(GoogleFormService::class, $serviceMock);

        $response = $this->postJson('/api/edm/event/delGoogleForm', ['id' => 1]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
    }

    public function test_update_response_status_delegates_to_service(): void
    {
        $serviceMock = Mockery::mock(GoogleFormService::class);
        $serviceMock->shouldReceive('updateResponseStatus')
            ->with('r1', 1)
            ->andReturn(['status' => true, 'message' => 'ok', 'data' => []]);
        $this->app->instance(GoogleFormService::class, $serviceMock);

        $response = $this->postJson('/api/edm/event/updateResponseStatus', [
            'response_id' => 'r1',
            'status' => 1,
        ]);

        $response->assertOk()->assertJson(['code' => 0, 'status' => true]);
    }
}
