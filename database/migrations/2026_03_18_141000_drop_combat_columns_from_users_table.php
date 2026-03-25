<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'hp',
                'max_hp',
                'strength',
                'dexterity',
                'constitution',
                'wit',
                'weapon',
                'weapon_id',
                'x',
                'y',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'hp')) {
                $table->integer('hp')->nullable();
            }
            if (!Schema::hasColumn('users', 'max_hp')) {
                $table->integer('max_hp')->nullable();
            }
            if (!Schema::hasColumn('users', 'strength')) {
                $table->integer('strength')->nullable();
            }
            if (!Schema::hasColumn('users', 'dexterity')) {
                $table->integer('dexterity')->nullable();
            }
            if (!Schema::hasColumn('users', 'constitution')) {
                $table->integer('constitution')->nullable();
            }
            if (!Schema::hasColumn('users', 'wit')) {
                $table->integer('wit')->nullable();
            }
            if (!Schema::hasColumn('users', 'weapon')) {
                $table->string('weapon')->nullable();
            }
            if (!Schema::hasColumn('users', 'weapon_id')) {
                $table->unsignedBigInteger('weapon_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'x')) {
                $table->integer('x')->nullable();
            }
            if (!Schema::hasColumn('users', 'y')) {
                $table->integer('y')->nullable();
            }
        });
    }
};
