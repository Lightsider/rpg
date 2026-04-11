<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('characters', 'off_hand_id')) {
            return;
        }

        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedBigInteger('off_hand_id')->nullable()->after('weapon_id');
            $table->foreign('off_hand_id')->references('id')->on('items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            if (Schema::hasColumn('characters', 'off_hand_id')) {
                $table->dropForeign(['off_hand_id']);
                $table->dropColumn('off_hand_id');
            }
        });
    }
};
