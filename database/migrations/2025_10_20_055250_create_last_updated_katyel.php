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
        Schema::create('katyel_last_updated', function (Blueprint $table) {
            $table->id();
            $table->integer('katyel_id');
            $table->integer('katyel_name');
            $table->string('temp');
            $table->dateTime('last_updated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_updated_katyel');
    }
};
