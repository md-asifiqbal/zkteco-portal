<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->integer('port')->default(4370)->after('ip_address');
            $table->string('comm_key')->nullable()->after('port');
            $table->boolean('is_cloud_supported')->default(false)->after('comm_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('port');
            $table->dropColumn('comm_key');
            $table->dropColumn('is_cloud_supported');
        });
    }
};
