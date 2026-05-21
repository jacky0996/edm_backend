<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event', 'img_url')) {
            Schema::table('event', function (Blueprint $table) {
                $table->dropColumn('img_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event', 'img_url')) {
            Schema::table('event', function (Blueprint $table) {
                $table->text('img_url')->nullable()->comment('活動圖片');
            });
        }
    }
};
