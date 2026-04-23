<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 為 Feature Test 準備 MeetingUser 所依賴的 meeting DB 連線
 * 將 meeting 連線改為 in-memory sqlite 並補上 users table
 */
trait PreparesMeetingConnection
{
    protected function prepareMeetingConnection(): void
    {
        config([
            'database.connections.meeting' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('meeting');
        DB::reconnect('meeting');

        Schema::connection('meeting')->dropIfExists('users');
        Schema::connection('meeting')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('enumber')->nullable();
            $table->string('old_enumber')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
}
