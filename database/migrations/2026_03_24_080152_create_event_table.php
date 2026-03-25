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
        Schema::create('event', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->string('event_number')->comment('活動單號Bxxx');
            $table->string('title', 255)->comment('活動名稱');
            $table->string('summary', 255)->comment('活動簡介')->nullable();
            $table->text('img_url')->comment('活動圖片');
            $table->longText('content')->comment('活動內容');
            $table->datetime('start_time')->comment('活動開始時間');
            $table->datetime('end_time')->comment('活動結束時間');
            $table->string('landmark', 255)->comment('活動地標')->nullable();
            $table->string('address', 255)->comment('活動地點');
            $table->integer('type')->comment('活動類別');
            $table->integer('status')->comment('活動建立狀態');
            $table->bigInteger('creator')->comment('建立者ID');
            $table->integer('is_approve')->comment('是否審核')->default(0);
            $table->integer('is_display')->comment('是否上架')->default(0);
            $table->integer('is_qrcode')->comment('是否產生QR')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('event');
    }
};
