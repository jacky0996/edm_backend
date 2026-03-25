<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('member', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->string('name', 10)->comment('姓名');
            $table->string('national_id', 20)->nullable()->comment('身分證');
            $table->integer('status')->default(0)->comment('狀態，0:尚未驗證, 1:正常');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['name']);
            $table->index(['national_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('member');
    }
};
