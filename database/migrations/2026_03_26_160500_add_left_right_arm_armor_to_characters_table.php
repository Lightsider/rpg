<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasLeft = Schema::hasColumn('characters', 'ad_armor_left_arm');
        $hasRight = Schema::hasColumn('characters', 'ad_armor_right_arm');

        Schema::table('characters', function (Blueprint $table) use ($hasLeft, $hasRight) {
            if (!$hasLeft) {
                $table->decimal('ad_armor_left_arm', 10, 4)->default(0)->after('ad_armor_hands');
            }
            if (!$hasRight) {
                $table->decimal('ad_armor_right_arm', 10, 4)->default(0)->after('ad_armor_left_arm');
            }
        });

        if (!$hasLeft || !$hasRight) {
            $formula = DB::raw('ROUND((ad_armor_chest * 0.5) + (ad_armor_hands * 0.5), 4)');
            DB::table('characters')->update([
                'ad_armor_left_arm' => $formula,
                'ad_armor_right_arm' => $formula,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            if (Schema::hasColumn('characters', 'ad_armor_right_arm')) {
                $table->dropColumn('ad_armor_right_arm');
            }
            if (Schema::hasColumn('characters', 'ad_armor_left_arm')) {
                $table->dropColumn('ad_armor_left_arm');
            }
        });
    }
};
