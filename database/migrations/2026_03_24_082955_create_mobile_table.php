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
        Schema::create('mobile', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->string('mobile', 20)->comment('行動電話');
            $table->index(['mobile']);
            $table->timestamps();
        });
        Schema::create('has_mobile', function (Blueprint $table) {
            $table->bigInteger('mobile_id')->unsigned();
            $table->bigInteger('mobileable_id')->unsigned();
            $table->string('mobileable_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile');
        Schema::dropIfExists('has_mobile');
    }
};
