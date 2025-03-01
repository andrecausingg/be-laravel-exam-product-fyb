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
        Schema::create('history_tbl', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid_history_id');

            $table->bigInteger('number_tbl_id');
            $table->uuid('uuid_tbl_id');
            
            $table->string('tbl_name');
            $table->string('column_name');
            $table->longText('value');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_tbl');
    }
};
