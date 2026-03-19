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
        Schema::table('characters', function (Blueprint $table) {
            $table->integer('strength')->default(10)->after('name');
            $table->integer('dexterity')->default(10)->after('strength');
            $table->integer('constitution')->default(10)->after('dexterity');
            $table->integer('wit')->default(10)->after('constitution');
            $table->unsignedBigInteger('weapon_id')->nullable()->after('wit');
            $table->string('weapon')->nullable()->after('weapon_id');
            $table->integer('x')->default(0)->after('location_id');
            $table->integer('y')->default(0)->after('x');
        });

        $mappable = [
            'strength',
            'dexterity',
            'constitution',
            'wit',
            'weapon_id',
            'weapon',
            'x',
            'y',
        ];

        $updates = [];
        foreach ($mappable as $column) {
            if (Schema::hasColumn('users', $column)) {
                $updates["characters.$column"] = DB::raw("users.$column");
            }
        }

        if (count($updates) > 0) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $selects = ['characters.id'];
                foreach (array_keys($updates) as $qualified) {
                    $column = str_replace('characters.', '', $qualified);
                    $selects[] = "users.$column as $column";
                }

                $rows = DB::table('characters')
                    ->join('users', 'characters.user_id', '=', 'users.id')
                    ->select($selects)
                    ->get();

                foreach ($rows as $row) {
                    $payload = [];
                    foreach (array_keys($updates) as $qualified) {
                        $column = str_replace('characters.', '', $qualified);
                        $payload[$column] = $row->$column;
                    }

                    DB::table('characters')
                        ->where('id', $row->id)
                        ->update($payload);
                }
            } elseif ($driver === 'pgsql') {
                $assignments = [];
                foreach (array_keys($updates) as $qualified) {
                    $column = str_replace('characters.', '', $qualified);
                    $assignments[] = "\"$column\" = users.\"$column\"";
                }

                DB::statement('UPDATE "characters" SET ' . implode(', ', $assignments) . ' FROM "users" WHERE "characters"."user_id" = "users"."id"');
            } else {
                DB::table('characters')
                    ->join('users', 'characters.user_id', '=', 'users.id')
                    ->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn([
                'strength',
                'dexterity',
                'constitution',
                'wit',
                'weapon_id',
                'weapon',
                'x',
                'y',
            ]);
        });
    }
};
