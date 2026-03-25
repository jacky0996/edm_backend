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
        Schema::create('event_relation', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->integer('event_id')->comment('活動 ID');
            $table->integer('member_id')->nullable()->comment('人員 ID');
            $table->integer('group_id')->nullable()->comment('群組 ID');
            $table->integer('signup_id')->nullable()->comment('報名資訊 ID');
            $table->integer('status')->default(0)->comment('審核狀態：0.未通過,1.通過(QRCode報到),2.通過(人工報到)');
            $table->datetime('invite_time')->nullable()->default(null)->comment('邀請時間');
            $table->datetime('checkin_time')->nullable()->default(null)->comment('報到時間');
            $table->bigInteger('mobile_id')->nullable()->default(null)->comment('手機ID');
            $table->bigInteger('organization_id')->nullable()->default(null)->comment('組織ID');
            $table->bigInteger('agent_id')->nullable()->default(null)->comment('代理商ID');
            $table->bigInteger('email_id')->nullable()->default(null)->comment('信箱ID');
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
        Schema::dropIfExists('event_relation');
    }
};
