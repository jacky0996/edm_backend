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
        Schema::create('email', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->string('email')->comment('電子郵件');
            $table->index(['email']);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('has_email', function (Blueprint $table) {
            $table->bigInteger('email_id')->unsigned();
            $table->bigInteger('emailable_id')->unsigned();
            $table->string('emailable_type');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email');
        Schema::dropIfExists('has_email');
    }
};
