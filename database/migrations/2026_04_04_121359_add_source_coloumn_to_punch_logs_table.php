<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'logs_db';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('punch_logs', function (Blueprint $table) {
            $table->string('source')->nullable()->after('stamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punch_logs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
