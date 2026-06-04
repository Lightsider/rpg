<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->decimal('effectiveness', 8, 4)->default(0)->after('damage_accumulator');
        });

        Schema::table('battle_participants', function (Blueprint $table) {
            $table->decimal('effectiveness', 8, 4)->default(0)->nullable()->after('damage_accumulator');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('effectiveness');
        });

        Schema::table('battle_participants', function (Blueprint $table) {
            $table->dropColumn('effectiveness');
        });
    }
};
