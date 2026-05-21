<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 對齊 group 表的建立者欄位：實際 DB 仍是舊 creator_enumber，
 * 程式碼已改為 creator_email。本 migration 將其轉成 creator_email。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('group', 'creator_enumber') && ! Schema::hasColumn('group', 'creator_email')) {
            Schema::table('group', function (Blueprint $table) {
                $table->string('creator_email', 255)->nullable()->after('note')->comment('建立者 email');
            });
            // 既有資料若有需要保留 enumber 內容可在此搬遷；目前用途已改為 email，不轉移舊值。
            Schema::table('group', function (Blueprint $table) {
                $table->dropColumn('creator_enumber');
            });

            return;
        }

        if (! Schema::hasColumn('group', 'creator_email')) {
            Schema::table('group', function (Blueprint $table) {
                $table->string('creator_email', 255)->nullable()->after('note')->comment('建立者 email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group', 'creator_email') && ! Schema::hasColumn('group', 'creator_enumber')) {
            Schema::table('group', function (Blueprint $table) {
                $table->string('creator_enumber', 10)->nullable()->after('note');
            });
            Schema::table('group', function (Blueprint $table) {
                $table->dropColumn('creator_email');
            });
        }
    }
};
