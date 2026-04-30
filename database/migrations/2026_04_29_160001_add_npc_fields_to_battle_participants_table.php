<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('battle_participants', function (Blueprint $table) {
            $table->boolean('is_npc')->default(false)->after('team');
            $table->foreignId('npc_template_id')->nullable()->after('is_npc')->constrained('npc_templates')->nullOnDelete();
            
            // Transient combat stats for NPCs
            $table->integer('hp')->nullable();
            $table->decimal('damage_accumulator', 8, 4)->default(0);
            $table->decimal('ad_armor_head', 8, 4)->default(0);
            $table->decimal('ad_armor_chest', 8, 4)->default(0);
            $table->decimal('ad_armor_legs', 8, 4)->default(0);
            $table->decimal('ad_armor_left_arm', 8, 4)->default(0);
            $table->decimal('ad_armor_right_arm', 8, 4)->default(0);
            
            // Make character_id nullable to support NPC participants
            $table->unsignedBigInteger('character_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('battle_participants', function (Blueprint $table) {
            $table->dropForeign(['npc_template_id']);
            $table->dropColumn([
                'npc_template_id', 'is_npc', 'hp', 'damage_accumulator',
                'ad_armor_head', 'ad_armor_chest', 'ad_armor_legs',
                'ad_armor_left_arm', 'ad_armor_right_arm'
            ]);
            
            $table->unsignedBigInteger('character_id')->nullable(false)->change();
        });
    }
};
