<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('google_form_responses', function (Blueprint $table) {
            $table->tinyInteger('status')->default(0)->comment('審核狀態: 0=待審核, 1=已通過, 2=不通過')->after('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_form_responses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
