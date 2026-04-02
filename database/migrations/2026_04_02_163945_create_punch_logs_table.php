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
        Schema::create('punch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('user_id');     // Employee ID from device
            $table->dateTime('timestamp'); // Time of punch
            $table->string('status');      // In/Out
            $table->string('verify_type'); // Face/Finger/Card
            $table->integer('stamp');      // Batch ID for tracking
            $table->timestamps();

            // Prevent duplicates at the database level
            $table->unique(['device_id', 'user_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('punch_logs');
    }
};
