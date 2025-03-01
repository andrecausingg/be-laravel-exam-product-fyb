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
        Schema::create('users_personal_access_token_tbl', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_users_personal_access_token_id');

            $table->bigInteger('number_user_id');
            $table->uuid('uuid_user_id');

            $table->string('tokenable_type');
            $table->string('name');
            $table->longText('token')->nullable();
            $table->json('abilities')->nullable();
            $table->string('status');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_personal_access_token_tbl');
    }
};
