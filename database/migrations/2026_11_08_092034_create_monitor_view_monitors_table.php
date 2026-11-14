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
        Schema::create('monitor_view_monitors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitor_view_id')->constrained('monitor_views')->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['monitor_view_id', 'monitor_id']);
            $table->index('monitor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_view_monitors');
    }
};
