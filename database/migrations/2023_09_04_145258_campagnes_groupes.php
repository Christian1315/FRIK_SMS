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
        Schema::create('campagnes_groupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId("campagne_id")->nullable()->constrained("campagnes", "id");
            $table->foreignId("groupe_id")->nullable()->constrained("campagnes", "id");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campagnes_groupes');
    }
};
