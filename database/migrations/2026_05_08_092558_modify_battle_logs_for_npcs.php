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
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropForeign(['target_id']);
            
            $table->bigInteger('actor_id')->nullable()->change();
            $table->bigInteger('target_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->bigInteger('actor_id')->nullable(false)->change();
            $table->bigInteger('target_id')->nullable(true)->change();
            $table->foreign('actor_id')->references('id')->on('characters')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('characters')->onDelete('cascade');
        });
    }
};
