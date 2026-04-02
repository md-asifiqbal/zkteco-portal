<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'logs_db';

    public function up(): void
    {
        Schema::create('punch_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('id'); // remove auto primary
            $table->unsignedBigInteger('device_id')->index();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('user_id');
            $table->dateTime('timestamp');
            $table->tinyInteger('status')->default(0);      // optimized
            $table->string('verify_type')->default(0); // optimized
            $table->integer('stamp');
            $table->timestamps();

            // Unique constraint
            $table->unique(['device_id', 'user_id', 'timestamp']);

            // Indexes
            $table->index(['tenant_id', 'timestamp']);
            $table->index(['device_id', 'stamp']);
            $table->index(['user_id']);
        });

        // 👉 Add AUTO_INCREMENT manually + composite PK
        DB::statement('
            ALTER TABLE punch_logs
            MODIFY id BIGINT UNSIGNED AUTO_INCREMENT,
            ADD PRIMARY KEY (id, timestamp)
        ');

        //         ALTER TABLE punch_logs
        // PARTITION BY RANGE (TO_DAYS(timestamp)) (
        //     PARTITION p_init VALUES LESS THAN (TO_DAYS('2026-01-01')),
        //     PARTITION pmax VALUES LESS THAN MAXVALUE
        // );
        // php artisan migrate --path=database/migrations_logs
    }

    public function down(): void
    {
        Schema::dropIfExists('punch_logs');
    }
};
