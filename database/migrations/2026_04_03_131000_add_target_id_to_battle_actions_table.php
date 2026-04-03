<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_actions', function (Blueprint $table) {
            if (!Schema::hasColumn('battle_actions', 'target_id')) {
                $table->foreignId('target_id')->nullable()->constrained('characters')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('battle_actions', function (Blueprint $table) {
            if (Schema::hasColumn('battle_actions', 'target_id')) {
                $table->dropConstrainedForeignId('target_id');
            }
        });
    }
};
