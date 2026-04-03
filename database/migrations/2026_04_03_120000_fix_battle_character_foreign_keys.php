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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('battle_participants')) {
            DB::statement('ALTER TABLE battle_participants DROP CONSTRAINT IF EXISTS battle_participants_user_id_foreign');

            if (!$this->constraintExists('battle_participants_character_id_foreign')) {
                Schema::table('battle_participants', function (Blueprint $table) {
                    $table->foreign('character_id', 'battle_participants_character_id_foreign')
                        ->references('id')
                        ->on('characters')
                        ->onDelete('cascade');
                });
            }
        }

        if (Schema::hasTable('battle_actions')) {
            DB::statement('ALTER TABLE battle_actions DROP CONSTRAINT IF EXISTS battle_actions_user_id_foreign');

            if (!$this->constraintExists('battle_actions_character_id_foreign')) {
                Schema::table('battle_actions', function (Blueprint $table) {
                    $table->foreign('character_id', 'battle_actions_character_id_foreign')
                        ->references('id')
                        ->on('characters')
                        ->onDelete('cascade');
                });
            }
        }

        if (Schema::hasTable('fighter_positions')) {
            DB::statement('ALTER TABLE fighter_positions DROP CONSTRAINT IF EXISTS fighter_positions_user_id_foreign');

            if (!$this->constraintExists('fighter_positions_character_id_foreign')) {
                Schema::table('fighter_positions', function (Blueprint $table) {
                    $table->foreign('character_id', 'fighter_positions_character_id_foreign')
                        ->references('id')
                        ->on('characters')
                        ->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('battle_participants')) {
            DB::statement('ALTER TABLE battle_participants DROP CONSTRAINT IF EXISTS battle_participants_character_id_foreign');

            if (!$this->constraintExists('battle_participants_user_id_foreign')) {
                Schema::table('battle_participants', function (Blueprint $table) {
                    $table->foreign('character_id', 'battle_participants_user_id_foreign')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                });
            }
        }

        if (Schema::hasTable('battle_actions')) {
            DB::statement('ALTER TABLE battle_actions DROP CONSTRAINT IF EXISTS battle_actions_character_id_foreign');

            if (!$this->constraintExists('battle_actions_user_id_foreign')) {
                Schema::table('battle_actions', function (Blueprint $table) {
                    $table->foreign('character_id', 'battle_actions_user_id_foreign')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                });
            }
        }

        if (Schema::hasTable('fighter_positions')) {
            DB::statement('ALTER TABLE fighter_positions DROP CONSTRAINT IF EXISTS fighter_positions_character_id_foreign');

            if (!$this->constraintExists('fighter_positions_user_id_foreign')) {
                Schema::table('fighter_positions', function (Blueprint $table) {
                    $table->foreign('character_id', 'fighter_positions_user_id_foreign')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                });
            }
        }
    }
};
