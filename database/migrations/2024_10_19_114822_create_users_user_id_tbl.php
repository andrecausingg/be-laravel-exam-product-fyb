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
        Schema::create('users_user_id_tbl', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_users_user_id_id');

            $table->bigInteger('number_user_id');
            $table->uuid('uuid_user_id');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_user_id_tbl');
    }
};
