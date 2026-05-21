<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event', 'creator_enumber')) {
            Schema::table('event', function (Blueprint $table) {
                $table->dropColumn('creator_enumber');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event', 'creator_enumber')) {
            Schema::table('event', function (Blueprint $table) {
                $table->string('creator_enumber', 10)->nullable()->after('creator_email');
            });
        }
    }
};
