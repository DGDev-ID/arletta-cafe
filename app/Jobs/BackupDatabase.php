<?php

namespace App\Jobs;

use App\Helpers\S3Helper;
use App\Models\BackupDatabase as BackupDatabaseModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupDatabase implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    public ?int $backupDatabaseId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $backupDatabaseId = null)
    {
        $this->backupDatabaseId = $backupDatabaseId;
    }

    /**
     * Helper to update backup progress safely.
     */
    protected function updateProgress(?BackupDatabaseModel $record, string $status, int $progress, string $message, ?string $error = null): void
    {
        if (!$record) {
            return;
        }

        $data = [
            'status'           => $status,
            'progress'         => $progress,
            'progress_message' => $message,
        ];

        if ($error !== null) {
            $data['error_message'] = $error;
        }

        $record->update($data);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[BackupDatabase] Starting PostgreSQL database backup...');

        $record = $this->backupDatabaseId
            ? BackupDatabaseModel::find($this->backupDatabaseId)
            : null;

        if (!$record) {
            $record = BackupDatabaseModel::create([
                'name'             => 'backup_' . now()->format('Y_m_d_His') . '.sql',
                'url'              => null,
                'status'           => BackupDatabaseModel::STATUS_PROCESSING,
                'progress'         => 15,
                'progress_message' => 'Menyiapkan konfigurasi database...',
            ]);
            $this->backupDatabaseId = $record->id;
        } else {
            $this->updateProgress($record, BackupDatabaseModel::STATUS_PROCESSING, 20, 'Menyiapkan konfigurasi database...');
        }

        $connection = config('database.default', 'pgsql');
        $dbConfig = config("database.connections.{$connection}", []);

        if (($dbConfig['driver'] ?? '') !== 'pgsql') {
            $dbConfig = config('database.connections.pgsql', []);
            if (($dbConfig['port'] ?? '') === '3306') {
                $dbConfig['port'] = '5432';
            }
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? '5432');
        $database = $dbConfig['database'] ?? 'postgres';
        $username = $dbConfig['username'] ?? 'postgres';
        $password = (string) ($dbConfig['password'] ?? '');

        if (!empty($dbConfig['url'])) {
            $parsed = parse_url($dbConfig['url']);
            if (isset($parsed['host'])) {
                $host = $parsed['host'];
            }
            if (isset($parsed['port'])) {
                $port = (string) $parsed['port'];
            }
            if (isset($parsed['path'])) {
                $database = ltrim($parsed['path'], '/');
            }
            if (isset($parsed['user'])) {
                $username = $parsed['user'];
            }
            if (isset($parsed['pass'])) {
                $password = (string) $parsed['pass'];
            }
        }

        // Generate file name and temporary path
        $timestamp = now()->format('Y_m_d_His');
        $fileName = "backup_{$database}_{$timestamp}.sql";

        $record->update(['name' => $fileName]);

        // Ensure temp directory exists in local storage disk
        if (!Storage::disk('local')->exists('temp')) {
            Storage::disk('local')->makeDirectory('temp');
        }

        $tempPath = Storage::disk('local')->path("temp/{$fileName}");

        // Determine pg_dump binary location (prefer PG 18 binary on Linux if installed)
        $pgDumpBinary = 'pg_dump';
        if (DIRECTORY_SEPARATOR === '/') {
            if (file_exists('/usr/lib/postgresql/18/bin/pg_dump')) {
                $pgDumpBinary = '/usr/lib/postgresql/18/bin/pg_dump';
            } elseif (file_exists('/usr/bin/pg_dump')) {
                $pgDumpBinary = '/usr/bin/pg_dump';
            }
        }

        $command = [
            $pgDumpBinary,
            '-w',
            '-h', (string) $host,
            '-p', (string) $port,
            '-U', (string) $username,
            '-d', (string) $database,
            '-f', $tempPath,
        ];

        Log::info("[BackupDatabase] Executing {$pgDumpBinary} for database '{$database}' at {$host}:{$port}");
        $this->updateProgress($record, BackupDatabaseModel::STATUS_PROCESSING, 35, 'Menjalankan pg_dump untuk menyalin data database...');

        $process = Process::timeout(3600)
            ->env([
                'PGPASSWORD' => $password,
            ])
            ->run($command);

        if (!$process->successful()) {
            $errorOutput = trim($process->errorOutput() ?: $process->output());
            Log::error("[BackupDatabase] pg_dump failed: {$errorOutput}");

            if (Storage::disk('local')->exists("temp/{$fileName}")) {
                Storage::disk('local')->delete("temp/{$fileName}");
            }

            $this->updateProgress($record, BackupDatabaseModel::STATUS_FAILED, 35, 'Gagal menjalankan pg_dump.', $errorOutput);
            throw new \RuntimeException("pg_dump backup failed: {$errorOutput}");
        }

        if (!Storage::disk('local')->exists("temp/{$fileName}") || Storage::disk('local')->size("temp/{$fileName}") === 0) {
            if (Storage::disk('local')->exists("temp/{$fileName}")) {
                Storage::disk('local')->delete("temp/{$fileName}");
            }
            $msg = 'pg_dump selesai tetapi file hasil kosong atau tidak ditemukan.';
            $this->updateProgress($record, BackupDatabaseModel::STATUS_FAILED, 50, $msg, $msg);
            throw new \RuntimeException("pg_dump completed but output file is empty or missing.");
        }

        $fileSize = Storage::disk('local')->size("temp/{$fileName}");
        Log::info("[BackupDatabase] Backup dump created successfully ({$fileSize} bytes). Uploading via S3Helper...");
        $this->updateProgress($record, BackupDatabaseModel::STATUS_PROCESSING, 65, 'Dump database selesai. Mengunggah file ke Cloud Storage...');

        // Upload to S3/Supabase Storage via S3Helper
        try {
            S3Helper::storeFileToS3('backup_databases', $fileName);
            $url = S3Helper::getUrlFileS3('backup_databases', $fileName);
            S3Helper::removeFileTemp($fileName);
        } catch (\Throwable $e) {
            Log::error("[BackupDatabase] Failed to upload to S3: " . $e->getMessage());
            S3Helper::removeFileTemp($fileName);
            $this->updateProgress($record, BackupDatabaseModel::STATUS_FAILED, 65, 'Gagal mengunggah file ke Cloud Storage.', $e->getMessage());
            throw $e;
        }

        $this->updateProgress($record, BackupDatabaseModel::STATUS_PROCESSING, 90, 'Menyimpan riwayat backup...');

        // Save backup record to database
        $record->update([
            'name'             => $fileName,
            'url'              => $url,
            'status'           => BackupDatabaseModel::STATUS_COMPLETED,
            'progress'         => 100,
            'progress_message' => 'Backup database selesai.',
            'error_message'    => null,
        ]);

        Log::info("[BackupDatabase] Backup successfully recorded with ID {$record->id}: {$url}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[BackupDatabase] Job failed: ' . $exception->getMessage());

        if ($this->backupDatabaseId) {
            $record = BackupDatabaseModel::find($this->backupDatabaseId);
            if ($record && $record->status !== BackupDatabaseModel::STATUS_FAILED) {
                $record->update([
                    'status'           => BackupDatabaseModel::STATUS_FAILED,
                    'progress_message' => 'Proses backup gagal.',
                    'error_message'    => $exception->getMessage(),
                ]);
            }
        }
    }
}
