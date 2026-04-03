<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'required_dexterity')) {
                $table->integer('required_dexterity')->default(0)->after('required_wit');
            }
            if (!Schema::hasColumn('items', 'required_constitution')) {
                $table->integer('required_constitution')->default(0)->after('required_dexterity');
            }
        });

        // Data fix for existing armor items
        DB::table('items')
            ->where('type', 'armor')
            ->where('name', 'like', 'Guardian %')
            ->update(['archetype' => 'tank']);

        DB::table('items')
            ->where('type', 'armor')
            ->where('name', 'like', 'Balanced %')
            ->update(['archetype' => 'universal']);

        DB::table('items')
            ->where('type', 'armor')
            ->where('name', 'like', 'Shadow %')
            ->update([
                'archetype' => 'dodge',
                'required_strength' => 0,
                'required_wit' => 0,
                'required_dexterity' => 4,
                'required_constitution' => 4,
            ]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'required_constitution')) {
                $table->dropColumn('required_constitution');
            }
            if (Schema::hasColumn('items', 'required_dexterity')) {
                $table->dropColumn('required_dexterity');
            }
        });
    }
};
