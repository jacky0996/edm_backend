<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 對齊 member 表結構：
 *  - 新增 creator_email (建立者 email)
 *  - 既有舊欄位 sales 改名為 sales_email，與 controller / migration 規格一致
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member', function (Blueprint $table) {
            if (! Schema::hasColumn('member', 'creator_email')) {
                $table->string('creator_email', 255)->nullable()->after('name')->comment('建立者 email');
            }
        });

        if (Schema::hasColumn('member', 'sales') && ! Schema::hasColumn('member', 'sales_email')) {
            Schema::table('member', function (Blueprint $table) {
                $table->string('sales_email', 255)->nullable()->after('status')->comment('業務 email');
            });
            // 將舊 sales 內容遷移到 sales_email (內容多為 email 或可作為 email 來源)
            DB::statement('UPDATE `member` SET sales_email = sales WHERE sales IS NOT NULL AND sales <> ""');
            Schema::table('member', function (Blueprint $table) {
                $table->dropColumn('sales');
            });

            return;
        }

        if (! Schema::hasColumn('member', 'sales_email')) {
            Schema::table('member', function (Blueprint $table) {
                $table->string('sales_email', 255)->nullable()->after('status')->comment('業務 email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('member', 'sales_email') && ! Schema::hasColumn('member', 'sales')) {
            Schema::table('member', function (Blueprint $table) {
                $table->string('sales', 20)->nullable()->after('status');
            });
            DB::statement('UPDATE `member` SET sales = sales_email WHERE sales_email IS NOT NULL');
            Schema::table('member', function (Blueprint $table) {
                $table->dropColumn('sales_email');
            });
        }

        if (Schema::hasColumn('member', 'creator_email')) {
            Schema::table('member', function (Blueprint $table) {
                $table->dropColumn('creator_email');
            });
        }
    }
};
