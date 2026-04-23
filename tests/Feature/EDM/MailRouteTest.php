<?php

namespace Tests\Feature\EDM;

use App\Jobs\Common\SendAwsMailJob;
use App\Models\EDM\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\PreparesMeetingConnection;
use Tests\TestCase;

class MailRouteTest extends TestCase
{
    use PreparesMeetingConnection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareMeetingConnection();
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'event_number' => 'E260423999',
            'title' => '測試信標題',
            'summary' => '',
            'content' => '測試信內文',
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
        ]);
    }

    public function test_invite_mail_requires_event_id_and_email_array(): void
    {
        $response = $this->postJson('/api/edm/mail/inviteMail', []);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
    }

    public function test_invite_mail_returns_error_when_event_missing(): void
    {
        $response = $this->postJson('/api/edm/mail/inviteMail', [
            'event_id' => 9999,
            'emails' => ['foo@example.com'],
        ]);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false, 'message' => '找不到對應的活動']);
    }

    public function test_invite_mail_rejects_when_no_valid_email_found(): void
    {
        $event = $this->makeEvent();

        Queue::fake();

        $response = $this->postJson('/api/edm/mail/inviteMail', [
            'event_id' => $event->id,
            'emails' => ['not-an-email', '@@wrong'],
        ]);

        $response->assertOk()->assertJson(['code' => 1, 'status' => false]);
        Queue::assertNothingPushed();
    }

    public function test_invite_mail_dispatches_job_with_valid_emails(): void
    {
        $event = $this->makeEvent();

        Queue::fake();

        $response = $this->postJson('/api/edm/mail/inviteMail', [
            'event_id' => $event->id,
            'emails' => ['valid@example.com', 'invalid-address', 'also.valid@test.io'],
        ]);

        $response->assertOk()->assertJson([
            'code' => 0,
            'status' => true,
            'count' => 2,
        ]);

        Queue::assertPushed(SendAwsMailJob::class, 1);
    }
}
