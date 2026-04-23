<?php

namespace Tests\Unit\Services;

use App\Models\EDM\Event;
use App\Models\Google\GoogleForm;
use App\Repositories\Google\GoogleFormRepository;
use App\Services\GoogleApiService;
use App\Services\GoogleFormService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class GoogleFormServiceTest extends TestCase
{
    /** @var GoogleApiService&MockInterface */
    private $googleApi;

    /** @var GoogleFormRepository&MockInterface */
    private $repository;

    private GoogleFormService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->googleApi = Mockery::mock(GoogleApiService::class);
        $this->repository = Mockery::mock(GoogleFormRepository::class);
        $this->service = new GoogleFormService($this->googleApi, $this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_form_config_uses_defaults_when_empty(): void
    {
        $result = $this->service->resolveFormConfig([], 'Fallback Title');

        $this->assertSame('Fallback Title', $result['title']);
        $this->assertNull($result['description']);
        $this->assertSame([], $result['standardFields']);
        $this->assertSame([], $result['customQuestions']);
    }

    public function test_resolve_form_config_prefers_root_params_over_config(): void
    {
        $input = [
            'title' => 'Root Title',
            'description' => 'Root desc',
            'standardFields' => ['name'],
            'customQuestions' => [['label' => 'Q1']],
            'config' => [
                'title' => 'Config Title',
                'description' => 'Config desc',
                'standardFields' => ['email'],
                'customQuestions' => [['label' => 'Q2']],
            ],
        ];

        $result = $this->service->resolveFormConfig($input, 'Default');

        $this->assertSame('Root Title', $result['title']);
        $this->assertSame('Root desc', $result['description']);
        $this->assertSame(['name'], $result['standardFields']);
        $this->assertSame([['label' => 'Q1']], $result['customQuestions']);
    }

    public function test_resolve_form_config_falls_back_to_nested_config(): void
    {
        $input = [
            'config' => [
                'title' => 'Config Title',
                'description' => 'Config desc',
                'standardFields' => ['email'],
                'customQuestions' => [['label' => 'Qc']],
            ],
        ];

        $result = $this->service->resolveFormConfig($input, 'Default');

        $this->assertSame('Config Title', $result['title']);
        $this->assertSame('Config desc', $result['description']);
        $this->assertSame(['email'], $result['standardFields']);
        $this->assertSame([['label' => 'Qc']], $result['customQuestions']);
    }

    public function test_build_questions_maps_standard_fields_and_merges_custom(): void
    {
        $mapping = [
            'name' => ['label' => '姓名', 'type' => 'text'],
            'email' => ['label' => '電子郵件', 'type' => 'text'],
        ];

        $result = $this->service->buildQuestions(
            ['name', 'email', 'unknown'],
            [['label' => '自訂題目', 'type' => 'text']],
            $mapping
        );

        $this->assertCount(3, $result);
        $this->assertSame('姓名', $result[0]['label']);
        $this->assertSame('電子郵件', $result[1]['label']);
        $this->assertSame('自訂題目', $result[2]['label']);
    }

    public function test_create_form_for_event_blocks_when_already_bound(): void
    {
        $event = new Event(['title' => 'Demo']);
        $event->id = 10;

        $existing = new GoogleForm(['event_id' => 10, 'form_id' => 'F1']);
        $this->repository->shouldReceive('findByEventId')->with(10)->once()->andReturn($existing);

        $result = $this->service->createFormForEvent($event, []);

        $this->assertFalse($result['status']);
        $this->assertSame($existing, $result['data']);
    }

    public function test_create_form_for_event_returns_error_when_google_api_fails(): void
    {
        $event = new Event(['title' => 'Demo']);
        $event->id = 10;

        $this->repository->shouldReceive('findByEventId')->with(10)->once()->andReturn(null);
        $this->googleApi->shouldReceive('createForm')->once()->andReturn([
            'status' => false,
            'error' => 'Google broke',
        ]);

        $result = $this->service->createFormForEvent($event, []);

        $this->assertFalse($result['status']);
        $this->assertSame('Google broke', $result['error']);
    }

    public function test_create_form_for_event_successfully_creates_and_binds(): void
    {
        $event = new Event(['title' => 'Demo']);
        $event->id = 10;

        $this->repository->shouldReceive('findByEventId')->with(10)->once()->andReturn(null);
        $this->googleApi->shouldReceive('createForm')->once()->with('Demo')->andReturn([
            'status' => true,
            'form_id' => 'FORM123',
            'responder_uri' => 'https://docs.google.com/forms/FORM123',
        ]);

        $this->googleApi->shouldReceive('batchUpdateQuestions')
            ->once()
            ->with('FORM123', Mockery::on(function ($questions) {
                return is_array($questions) && count($questions) === 2;
            }), null);

        $created = new GoogleForm([
            'event_id' => 10,
            'form_id' => 'FORM123',
            'form_url' => 'https://docs.google.com/forms/FORM123',
            'type' => 'google_form',
        ]);

        $this->repository->shouldReceive('createForm')->once()->andReturn($created);

        $result = $this->service->createFormForEvent($event, [
            'standardFields' => ['name', 'email'],
        ]);

        $this->assertTrue($result['status']);
        $this->assertSame($created, $result['data']);
    }

    public function test_sync_form_requires_id_or_event_id(): void
    {
        $result = $this->service->syncForm(null, null, []);

        $this->assertFalse($result['status']);
        $this->assertSame('缺少必要參數', $result['message']);
    }

    public function test_sync_form_returns_error_when_record_missing(): void
    {
        $this->repository->shouldReceive('findByIdOrEventId')->with(1, null)->andReturn(null);

        $result = $this->service->syncForm(1, null, []);

        $this->assertFalse($result['status']);
        $this->assertSame('找不到對應的表單紀錄', $result['message']);
    }

    public function test_sync_form_updates_type_when_provided(): void
    {
        $form = Mockery::mock(GoogleForm::class)->makePartial();
        $form->form_id = 'FORM123';

        $this->repository->shouldReceive('findByIdOrEventId')->andReturn($form);
        $this->googleApi->shouldReceive('syncFormItems')->once();
        $form->shouldReceive('save')->once()->andReturnTrue();

        $result = $this->service->syncForm(1, null, [
            'type' => 'custom_type',
            'standardFields' => ['name'],
            'customQuestions' => [],
        ]);

        $this->assertTrue($result['status']);
        $this->assertSame('custom_type', $form->type);
    }

    public function test_delete_form_validates_id(): void
    {
        $result = $this->service->deleteForm(null);

        $this->assertFalse($result['status']);
    }

    public function test_delete_form_returns_error_when_not_found(): void
    {
        $this->repository->shouldReceive('findById')->with(9)->andReturn(null);

        $result = $this->service->deleteForm(9);

        $this->assertFalse($result['status']);
        $this->assertSame('找不到該筆 Google 表單紀錄', $result['message']);
    }

    public function test_delete_form_delegates_to_repository(): void
    {
        $form = new GoogleForm(['form_id' => 'FORM1']);
        $this->repository->shouldReceive('findById')->with(1)->andReturn($form);
        $this->repository->shouldReceive('deleteForm')->with($form)->once()->andReturnTrue();

        $result = $this->service->deleteForm(1);

        $this->assertTrue($result['status']);
    }

    public function test_update_response_status_requires_params(): void
    {
        $this->assertFalse($this->service->updateResponseStatus(null, 1)['status']);
        $this->assertFalse($this->service->updateResponseStatus('abc', null)['status']);
    }

    public function test_update_response_status_returns_error_when_not_found(): void
    {
        $this->repository->shouldReceive('findResponseByGoogleId')->with('abc')->andReturn(null);

        $result = $this->service->updateResponseStatus('abc', 1);

        $this->assertFalse($result['status']);
        $this->assertSame('找不到該筆報名紀錄', $result['message']);
    }

    public function test_approve_pending_delegates_to_repository(): void
    {
        $this->repository->shouldReceive('approvePendingByEventId')->with(77)->andReturn(3);

        $this->assertSame(3, $this->service->approvePendingByEventId(77));
    }
}
