<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monitor_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();

            $table->integer('content_order')->default(0);

            $table->unsignedInteger('display_time')->nullable();

            // Default false → content must be explicitly activated (preview-before-live).
            $table->boolean('active')->default(false);

            $table->timestamps();

            $table->unique(['monitor_id', 'content_id']);
            $table->index('content_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitor_contents');
    }
};
