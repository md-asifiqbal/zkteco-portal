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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('serial_number')->unique();
            $table->string('ip_address')->nullable();
            $table->string('model')->nullable();
            $table->string('timezone')->nullable();
            $table->integer('heartbeat_interval')->default(15); // in seconds
            $table->dateTime('last_heartbeat')->nullable();
            $table->integer('last_stamp')->default(0);
            $table->integer('last_op_stamp')->default(0);
            $table->string('firmware_version')->nullable();
            $table->integer('user_count')->default(0);
            $table->integer('face_count')->default(0);
            $table->integer('fingerprint_count')->default(0);
            $table->integer('card_count')->default(0);

            // Settings
            $table->boolean('is_support_cloud')->default(true);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
