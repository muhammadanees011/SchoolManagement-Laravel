<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Student;
use App\Models\Staff;

class ResetFSM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:fsm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset FSM amount to 0 for students and staff';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Reset FSM Cron Job running at ". now());

        $backupPath = $this->exportStudentsAndStaffToSql();
        $this->info('Backup saved to: ' . $backupPath);

        $deleted = $this->deleteOldBackups();
        if ($deleted > 0) {
            $this->info("Deleted {$deleted} backup(s) older than 7 working days.");
        }

        Student::whereRaw('fsm_amount IS NOT NULL')->update(['fsm_amount' => 0]);
        Staff::whereRaw('fsm_amount IS NOT NULL')->update(['fsm_amount' => 0]);

        $this->info('FSM amounts reset to 0 for students and staff.');
    }

    /**
     * Export students and staff tables to an SQL file in storage.
     */
    protected function exportStudentsAndStaffToSql(): string
    {
        $directory = storage_path('app/fsm_backups');
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = 'students_staff_' . now()->format('Y-m-d_His') . '.sql';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        $sql = "-- FSM backup before reset:fsm\n";
        $sql .= "-- Generated at: " . now()->toDateTimeString() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach (['students', 'staff'] as $table) {
            $sql .= $this->buildTableExportSql($table);
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        File::put($path, $sql);

        return $path;
    }

    /**
     * Delete FSM backup SQL files older than 7 working days.
     */
    protected function deleteOldBackups(): int
    {
        $directory = storage_path('app/fsm_backups');
        if (!File::isDirectory($directory)) {
            return 0;
        }

        $cutoff = now()->subWeekdays(7)->startOfDay()->getTimestamp();
        $deleted = 0;

        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'sql') {
                continue;
            }

            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Build CREATE TABLE + INSERT SQL for a single table.
     */
    protected function buildTableExportSql(string $table): string
    {
        $sql = "-- ----------------------------\n";
        $sql .= "-- Table: {$table}\n";
        $sql .= "-- ----------------------------\n";

        $create = DB::select("SHOW CREATE TABLE `{$table}`");
        $createStatement = $create[0]->{'Create Table'} ?? null;
        if ($createStatement) {
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createStatement . ";\n\n";
        }

        $rows = DB::table($table)->get();
        if ($rows->isEmpty()) {
            $sql .= "-- No rows in {$table}\n\n";
            return $sql;
        }

        foreach ($rows as $row) {
            $data = (array) $row;
            $columns = array_map(fn ($col) => "`{$col}`", array_keys($data));
            $values = array_map(function ($value) {
                if (is_null($value)) {
                    return 'NULL';
                }
                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }
                if (is_int($value) || is_float($value)) {
                    return (string) $value;
                }
                return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
            }, array_values($data));

            $sql .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        }

        $sql .= "\n";

        return $sql;
    }
}
