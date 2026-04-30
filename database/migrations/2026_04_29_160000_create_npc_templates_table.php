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
        Schema::create('npc_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // humanoid, beast
            
            // Stats
            $table->integer('strength')->default(1);
            $table->integer('agility')->default(1);
            $table->integer('constitution')->default(1);
            $table->integer('wit')->default(1);
            $table->integer('level')->default(1);
            
            $table->string('behavior_model')->default('reach_and_hit');
            $table->json('equipment_config')->nullable(); // Item IDs for humanoid NPCs
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('npc_templates');
    }
};
