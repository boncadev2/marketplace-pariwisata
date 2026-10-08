<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            File::deleteDirectory($backupDir);
        }
    }

    protected function tearDown(): void
    {
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            File::deleteDirectory($backupDir);
        }

        parent::tearDown();
    }

    public function test_backup_creates_sql_and_checksum_files(): void
    {
        $this->artisan('app:backup-database')
            ->assertSuccessful();

        $backupDir = storage_path('app/backups');
        $this->assertTrue(File::isDirectory($backupDir));

        $files = File::files($backupDir);
        $sqlFiles = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.sql'));
        $shaFiles = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.sha256'));

        $this->assertCount(1, $sqlFiles);
        $this->assertCount(1, $shaFiles);

        $sqlFile = reset($sqlFiles);
        $shaFile = reset($shaFiles);

        $checksum = hash_file('sha256', $sqlFile->getPathname());
        $this->assertStringContainsString($checksum, File::get($shaFile->getPathname()));
        $this->assertStringContainsString('Wisata Daerah Database Backup', File::get($sqlFile->getPathname()));
    }

    public function test_backup_compressed_creates_valid_gzip_archive(): void
    {
        $this->artisan('app:backup-database', ['--compress' => true])
            ->assertSuccessful();

        $backupDir = storage_path('app/backups');
        $files = File::files($backupDir);
        $gzFiles = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'));

        $this->assertCount(1, $gzFiles);
        $gzFile = reset($gzFiles);

        // Verify decompressed content
        $decompressed = gzdecode(File::get($gzFile->getPathname()));
        $this->assertNotFalse($decompressed);
        $this->assertStringContainsString('Wisata Daerah Database Backup', $decompressed);
    }

    public function test_backup_retention_prunes_older_files(): void
    {
        $backupDir = storage_path('app/backups');
        File::makeDirectory($backupDir, 0700, true);

        // Create an expired dummy backup (8 days ago)
        $oldFile = "{$backupDir}/backup-2026-01-01-000000.sql";
        file_put_contents($oldFile, '-- old backup');
        touch($oldFile, now()->subDays(8)->timestamp);

        $this->artisan('app:backup-database', ['--keep-days' => 7])
            ->assertSuccessful();

        $this->assertFileDoesNotExist($oldFile);
    }
}
