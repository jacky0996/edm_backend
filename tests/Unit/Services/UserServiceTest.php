<?php

namespace Tests\Unit\Services;

use App\Services\UserService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService;
    }

    private function makeRequestWithUser(array $user): Request
    {
        $encoded = base64_encode(json_encode($user));
        $request = Request::create('/test', 'POST');
        $request->headers->set('X-User-Info', $encoded);

        return $request;
    }

    public function test_get_user_from_header_returns_null_when_header_missing(): void
    {
        $request = Request::create('/test', 'POST');

        $this->assertNull($this->service->getUserFromHeader($request));
    }

    public function test_get_user_from_header_returns_array_when_header_is_valid(): void
    {
        $user = ['enumber' => 'E001', 'realName' => '王小明', 'uid' => 7];
        $request = $this->makeRequestWithUser($user);

        $this->assertSame($user, $this->service->getUserFromHeader($request));
    }

    public function test_get_user_from_header_returns_null_when_payload_not_array(): void
    {
        $request = Request::create('/test', 'POST');
        $request->headers->set('X-User-Info', base64_encode(json_encode('string-not-array')));

        $this->assertNull($this->service->getUserFromHeader($request));
    }

    public function test_get_user_from_header_returns_null_when_payload_corrupted(): void
    {
        $request = Request::create('/test', 'POST');
        $request->headers->set('X-User-Info', 'not-valid-base64-or-json-~~~');

        $this->assertNull($this->service->getUserFromHeader($request));
    }

    public function test_get_user_id_prefers_uid_then_user_id(): void
    {
        $request = $this->makeRequestWithUser(['uid' => 123, 'userId' => 456]);
        $this->assertSame(123, $this->service->getUserId($request));

        $request = $this->makeRequestWithUser(['userId' => 456]);
        $this->assertSame(456, $this->service->getUserId($request));
    }

    public function test_get_user_id_returns_null_without_header(): void
    {
        $request = Request::create('/test', 'POST');

        $this->assertNull($this->service->getUserId($request));
    }

    public function test_get_user_name_falls_back_to_guest(): void
    {
        $request = Request::create('/test', 'POST');
        $this->assertSame('Guest', $this->service->getUserName($request));

        $request = $this->makeRequestWithUser(['name' => 'Sam']);
        $this->assertSame('Sam', $this->service->getUserName($request));

        $request = $this->makeRequestWithUser(['realName' => '阿明', 'name' => 'Sam']);
        $this->assertSame('阿明', $this->service->getUserName($request));
    }
}
