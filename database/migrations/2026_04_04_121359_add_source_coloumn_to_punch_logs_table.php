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
            $table->tinyInteger('source')->nullable()->default(1)->after('stamp')->comment('1:push, 2:pull');
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
