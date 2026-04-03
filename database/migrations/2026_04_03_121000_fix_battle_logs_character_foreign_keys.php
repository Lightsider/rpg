<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function constraintExists(string $constraintName): bool
    {
        $rows = DB::select('select 1 from pg_constraint where conname = ?', [$constraintName]);

        return !empty($rows);
    }

    public function up(): void
    {
        if (!Schema::hasTable('battle_logs')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE battle_logs DROP CONSTRAINT IF EXISTS battle_logs_actor_id_foreign');
        DB::statement('ALTER TABLE battle_logs DROP CONSTRAINT IF EXISTS battle_logs_target_id_foreign');

        if (!$this->constraintExists('battle_logs_actor_id_foreign')) {
            Schema::table('battle_logs', function (Blueprint $table) {
                $table->foreign('actor_id', 'battle_logs_actor_id_foreign')
                    ->references('id')
                    ->on('characters')
                    ->onDelete('cascade');
            });
        }

        if (!$this->constraintExists('battle_logs_target_id_foreign')) {
            Schema::table('battle_logs', function (Blueprint $table) {
                $table->foreign('target_id', 'battle_logs_target_id_foreign')
                    ->references('id')
                    ->on('characters')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('battle_logs')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE battle_logs DROP CONSTRAINT IF EXISTS battle_logs_actor_id_foreign');
        DB::statement('ALTER TABLE battle_logs DROP CONSTRAINT IF EXISTS battle_logs_target_id_foreign');

        if (!$this->constraintExists('battle_logs_actor_id_foreign')) {
            Schema::table('battle_logs', function (Blueprint $table) {
                $table->foreign('actor_id', 'battle_logs_actor_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }

        if (!$this->constraintExists('battle_logs_target_id_foreign')) {
            Schema::table('battle_logs', function (Blueprint $table) {
                $table->foreign('target_id', 'battle_logs_target_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    }
};
