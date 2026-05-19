<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->integer('unallocated_stats')->default(0)->after('wit');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('unallocated_stats');
        });
    }
};
