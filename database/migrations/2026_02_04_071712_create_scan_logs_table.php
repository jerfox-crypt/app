<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->string('rfid', 50);
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('sid', 50)->nullable(); // From studinfo table
            $table->string('lrn', 20)->nullable(); // From studinfo table
            $table->string('scan_type', 20)->default('attendance'); // attendance, entry, exit
            $table->string('location', 100)->nullable(); // gate1, library, etc.
            $table->string('status', 20); // success, not_found, error, duplicate
            $table->text('message')->nullable();
            $table->json('metadata')->nullable(); // Store additional data
            $table->timestamp('scan_time')->useCurrent();
            
            $table->index('rfid');
            $table->index('student_id');
            $table->index('scan_time');
            $table->index(['rfid', 'scan_time']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('scan_logs');
    }
};