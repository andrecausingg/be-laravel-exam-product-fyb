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
        Schema::create('logs_tbl', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_logs_id');

            $table->bigInteger('number_user_id')->nullable();
            $table->uuid('uuid_user_id')->nullable();

            $table->boolean('is_log_user')->default(false);

            $table->text('controller_class_name');
            $table->text('function_name');
            $table->text('model_class_name')->nullable();
            $table->text('activity');
            $table->longText('payload');
            $table->longText('success_result_details')->nullable();
            $table->longText('error_result_details')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_tbl');
    }
};
