<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('partitions:manage {--months=6}')]
#[Description('Create future monthly partitions for punch_logs table')]
class ManagePunchLogPartitions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = DB::connection('logs_db');
        $table = 'punch_logs';

        $months = (int) $this->option('months') ?: 6;

        $this->info("🔧 Managing partitions for next {$months} months...");

        for ($i = 0; $i < $months; $i++) {

            $date = Carbon::now()->startOfMonth()->addMonths($i);

            $partitionName = 'p'.$date->format('Ym');
            $nextMonthDate = $date->copy()->addMonth()->format('Y-m-d');

            // ✅ Check if partition already exists
            $exists = $connection->selectOne('
                SELECT PARTITION_NAME
                FROM INFORMATION_SCHEMA.PARTITIONS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND PARTITION_NAME = ?
            ', [$table, $partitionName]);

            if ($exists) {
                $this->line("✔ Exists: {$partitionName}");

                continue;
            }

            // ⚠️ Use REORGANIZE (because of pmax)
            $sql = "
                ALTER TABLE {$table}
                REORGANIZE PARTITION pmax INTO (
                    PARTITION {$partitionName}
                    VALUES LESS THAN (TO_DAYS('{$nextMonthDate}')),
                    PARTITION pmax VALUES LESS THAN MAXVALUE
                )
            ";

            try {
                $connection->statement($sql);
                $this->info("✅ Created: {$partitionName}");
            } catch (\Throwable $e) {
                $this->error("❌ Failed: {$partitionName}");
                $this->error($e->getMessage());
            }
        }

        $this->info('🎉 Partition management completed!');

        return Command::SUCCESS;
    }
}
