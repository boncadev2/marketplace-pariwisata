<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('app:backup-database {--compress : Compress output with gzip} {--keep-days=7 : Retention period in days for older backups}')]
#[Description('Create an automated database snapshot with SHA-256 checksum and retention management')]
class BackupDatabaseCommand extends Command
{
    public function handle(): int
    {
        $connection = config('database.default');
        $this->info("Memulai pembuatan cadangan database ({$connection})...");

        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0700, true);
        }

        $timestamp = now()->format('Y-m-d-His');
        $compress = (bool) $this->option('compress');
        $filename = "backup-{$timestamp}.sql" . ($compress ? '.gz' : '');
        $filepath = "{$backupDir}/{$filename}";

        $startTime = microtime(true);
        if ($connection === 'mysql') {
            $database = config("database.connections.{$connection}.database");
            $rawTables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', [$database]);
            $tables = array_map(fn ($row) => $row->TABLE_NAME ?? $row->table_name, $rawTables);
        } else {
            $tables = Schema::getTableListing();
        }
        $totalTables = count($tables);

        $handle = $compress ? gzopen($filepath, 'w9') : fopen($filepath, 'w');
        if (! $handle) {
            $this->error("Gagal membuka file tujuan cadangan: {$filepath}");

            return self::FAILURE;
        }

        $write = function (string $content) use ($handle, $compress): void {
            if ($compress) {
                gzwrite($handle, $content);
            } else {
                fwrite($handle, $content);
            }
        };

        $write("-- Wisata Daerah Database Backup\n");
        $write("-- Created at: " . now()->toIso8601String() . "\n");
        $write("-- Connection: {$connection}\n");
        $write("-- Tables count: {$totalTables}\n\n");

        if ($connection === 'mysql') {
            $write("SET FOREIGN_KEY_CHECKS=0;\n");
            $write("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        }

        $progressBar = $this->output->createProgressBar($totalTables);
        $progressBar->start();

        foreach ($tables as $table) {
            $write("\n-- --------------------------------------------------------\n");
            $write("-- Struktur & Data Tabel `{$table}`\n");
            $write("-- --------------------------------------------------------\n\n");

            // Dump table creation statement
            if ($connection === 'sqlite') {
                $createRow = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
                if ($createRow && ! empty($createRow->sql)) {
                    $write("DROP TABLE IF EXISTS `{$table}`;\n");
                    $write($createRow->sql . ";\n\n");
                }
            } else {
                $createRow = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                if ($createRow) {
                    $prop = 'Create Table';
                    $sql = $createRow->$prop ?? null;
                    if ($sql) {
                        $write("DROP TABLE IF EXISTS `{$table}`;\n");
                        $write($sql . ";\n\n");
                    }
                }
            }

            // Dump table rows
            DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($table, $write, $connection): void {
                if ($rows->isEmpty()) {
                    return;
                }

                $columns = array_keys((array) $rows->first());
                $escapedCols = array_map(fn ($col) => "`{$col}`", $columns);
                $colsSql = implode(', ', $escapedCols);

                foreach ($rows as $row) {
                    $values = [];
                    foreach ((array) $row as $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($val) && ! is_string($val)) {
                            $values[] = (string) $val;
                        } else {
                            $escaped = addslashes((string) $val);
                            $escaped = str_replace(["\r", "\n"], ["\\r", "\\n"], $escaped);
                            $values[] = "'{$escaped}'";
                        }
                    }
                    $write("INSERT INTO `{$table}` ({$colsSql}) VALUES (" . implode(', ', $values) . ");\n");
                }
            });

            $progressBar->advance();
        }

        if ($connection === 'mysql') {
            $write("\nSET FOREIGN_KEY_CHECKS=1;\n");
        }

        if ($compress) {
            gzclose($handle);
        } else {
            fclose($handle);
        }

        $progressBar->finish();
        $this->newLine(2);

        // Compute SHA-256 Checksum
        $checksum = hash_file('sha256', $filepath);
        $checksumFile = "{$filepath}.sha256";
        file_put_contents($checksumFile, "{$checksum}  {$filename}\n");
        chmod($filepath, 0600);
        chmod($checksumFile, 0600);

        $duration = round(microtime(true) - $startTime, 2);
        $fileSize = round(filesize($filepath) / 1024, 2);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Berkas Cadangan', $filename],
                ['Lokasi', $filepath],
                ['Ukuran', "{$fileSize} KB"],
                ['Total Tabel', $totalTables],
                ['Checksum SHA-256', $checksum],
                ['Durasi Proses', "{$duration} detik"],
            ]
        );

        // Retention management
        $keepDays = (int) $this->option('keep-days');
        if ($keepDays > 0) {
            $prunedCount = $this->pruneOldBackups($backupDir, $keepDays);
            if ($prunedCount > 0) {
                $this->info("Menghapus {$prunedCount} berkas cadangan lama yang melebihi batas simpan {$keepDays} hari.");
            }
        }

        $this->info('Cadangan database berhasil dibuat dengan aman.');

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $directory, int $keepDays): int
    {
        $cutoff = now()->subDays($keepDays)->timestamp;
        $pruned = 0;

        foreach (File::files($directory) as $file) {
            $filename = $file->getFilename();
            if (! str_starts_with($filename, 'backup-')) {
                continue;
            }

            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $pruned++;
            }
        }

        return $pruned;
    }
}
