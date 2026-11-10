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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();

            $table->string('content_name');

            $table->string('client_file_name')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // content_type: 1=image, 2=video, 3=text, 4=flash, 5=url
            $table->unsignedTinyInteger('content_type');

            $table->boolean('is_auto_login')->default(false);

            $table->boolean('deleted')->default(false);

            //   Raw SQL needs "order". Kept as spec'd.
            $table->integer('order')->default(0);

            $table->timestamp('uploaded_at')->nullable();

            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            $table->foreignId('content_url_user_password_id')
                ->nullable()
                ->constrained('content_url_user_passwords')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('user_id');
            $table->index('content_type');
            $table->index('deleted');
            $table->index('from_date');
            $table->index('to_date');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
