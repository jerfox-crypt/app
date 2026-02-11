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
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->string('rfid', 50)->index();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('person_type', 20)->nullable(); // 'teacher' or 'student'
            $table->string('status', 20); // 'success', 'error', 'not_found', 'duplicate', 'rate_limited'
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('method', 10)->nullable(); // GET, POST
            $table->string('endpoint')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_logs');
    }
};
