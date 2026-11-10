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
        Schema::create('departments', function (Blueprint $table) {

            $table->id();

            $table->string("department_name");

            $table->text("daily_message")->nullable();

            $table->boolean('is_child')->default(false);

            $table->boolean('lock')->nullable()->default(false);

            $table->foreignId('office_id')
                ->constrained('offices')
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['office_id', 'department_name']);
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
