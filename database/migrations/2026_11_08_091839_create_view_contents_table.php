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
        Schema::create('view_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('view_id')->constrained('views')->cascadeOnDelete();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();

            // TODO(view_contents): does this pivot carry content_order /
            //   display_time / active like monitor_contents? Or are those
            //   only set at monitor level? Kept minimal for now.

            $table->timestamps();

            $table->unique(['view_id', 'content_id']);
            $table->index('content_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('view_contents');
    }
};
