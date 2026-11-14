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
        Schema::create('monitors', function (Blueprint $table) {

            $table->id();

            $table->ipAddress('ip_address')->unique();   // 45 chars fits IPv6

            $table->string('host_name')->nullable();

            $table->string('mac_address', 17)->nullable()->unique(); // aa:bb:cc:dd:ee:ff

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            $table->string('floor', 255)->nullable();

            $table->string('wing', 255)->nullable();

            // String (not enum) so we can add statuses without a migration.
            // TODO Adjust the default to match whatever the domain shall use.
            $table->string('status')->nullable();

            $table->string('lock')->nullable();

            $table->text('description')->nullable();

            $table->string('platform', 255)->nullable();

            $table->string('owner', 255)->nullable();

            $table->timestamps();

            $table->index('department_id');
            $table->index('status');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
