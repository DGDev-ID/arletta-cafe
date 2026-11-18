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

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[BackupDatabase] Starting PostgreSQL database backup...');

        $connection = config('database.default', 'pgsql');
        $dbConfig = config("database.connections.{$connection}", []);

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
            '-h', (string) $host,
            '-p', (string) $port,
            '-U', (string) $username,
            '-d', (string) $database,
            '-f', $tempPath,
        ];

        Log::info("[BackupDatabase] Executing {$pgDumpBinary} for database '{$database}' at {$host}:{$port}");

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

            throw new \RuntimeException("pg_dump backup failed: {$errorOutput}");
        }

        if (!Storage::disk('local')->exists("temp/{$fileName}") || Storage::disk('local')->size("temp/{$fileName}") === 0) {
            if (Storage::disk('local')->exists("temp/{$fileName}")) {
                Storage::disk('local')->delete("temp/{$fileName}");
            }
            throw new \RuntimeException("pg_dump completed but output file is empty or missing.");
        }

        $fileSize = Storage::disk('local')->size("temp/{$fileName}");
        Log::info("[BackupDatabase] Backup dump created successfully ({$fileSize} bytes). Uploading via S3Helper...");

        // Upload to S3/Supabase Storage via S3Helper
        try {
            S3Helper::storeFileToS3('backup_databases', $fileName);
            $url = S3Helper::getUrlFileS3('backup_databases', $fileName);
            S3Helper::removeFileTemp($fileName);
        } catch (\Throwable $e) {
            Log::error("[BackupDatabase] Failed to upload to S3: " . $e->getMessage());
            S3Helper::removeFileTemp($fileName);
            throw $e;
        }

        // Save backup record to database
        $backup = BackupDatabaseModel::create([
            'name' => $fileName,
            'url'  => $url,
        ]);

        Log::info("[BackupDatabase] Backup successfully recorded with ID {$backup->id}: {$url}");
    }
}
