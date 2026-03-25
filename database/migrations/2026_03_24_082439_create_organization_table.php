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
        Schema::create('organization', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement()->unsigned();
            $table->string('name')->comment('組織名稱');
            $table->string('department')->nullable()->comment('部門');
            $table->string('title')->nullable()->comment('職稱');
            $table->string('codename')->nullable()->comment('erp公司代號');
            $table->string('codename')->nullable()->comment('erp公司代號');
            $table->index(['name']);
            $table->index(['department']);
            $table->index(['title']);
            $table->index(['codename']);
            $table->timestamps();
        });
        Schema::create('has_organization', function (Blueprint $table) {
            $table->bigInteger('organization_id')->unsigned();
            $table->bigInteger('organizationable_id')->unsigned();
            $table->string('organizationable_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization');
        Schema::dropIfExists('has_organization');
    }
};
