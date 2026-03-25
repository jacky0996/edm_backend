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
        Schema::create('group', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->string('name', 100)->comment('群組名稱');
            $table->text('note')->nullable()->comment('備註');
            $table->integer('creator_id')->nullable()->comment('建立者');
            $table->integer('status')->default(1)->comment('狀態');
            $table->timestamps();
            $table->index(['name']);
        });

        Schema::create('has_group', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->bigInteger('group_id')->unsigned();
            $table->bigInteger('groupable_id')->unsigned();
            $table->string('groupable_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('has_group');
        Schema::dropIfExists('group');
    }
};
