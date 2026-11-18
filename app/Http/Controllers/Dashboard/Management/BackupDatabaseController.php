<?php

namespace App\Http\Controllers\Dashboard\Management;

use App\Helpers\S3Helper;
use App\Http\Controllers\Controller;
use App\Jobs\BackupDatabase as BackupDatabaseJob;
use App\Models\BackupDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BackupDatabaseController extends Controller
{
    /**
     * Ensure only Super Admin can access backup database endpoints.
     */
    protected function authorizeSuperAdmin(Request $request): void
    {
        if (!$request->user()?->isSuperadmin()) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');
        }
    }

    /**
     * Display a listing of database backups.
     */
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        $search = $request->input('search', '');

        $backups = BackupDatabase::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (BackupDatabase $backup) => [
                'id'                   => $backup->id,
                'name'                 => $backup->name,
                'url'                  => $backup->url,
                'status'               => $backup->status ?? BackupDatabase::STATUS_COMPLETED,
                'progress'             => $backup->progress ?? 100,
                'progress_message'     => $backup->progress_message,
                'error_message'        => $backup->error_message,
                'created_at'           => $backup->created_at?->format('Y-m-d H:i:s'),
                'created_at_formatted' => $backup->created_at?->translatedFormat('d M Y, H:i') . ' WIB',
                'created_at_diff'      => $backup->created_at?->diffForHumans(),
            ]);

        $activeBackup = BackupDatabase::query()
            ->whereIn('status', [BackupDatabase::STATUS_PENDING, BackupDatabase::STATUS_PROCESSING])
            ->latest('id')
            ->first(['id', 'name', 'status', 'progress', 'progress_message', 'error_message', 'created_at']);

        return Inertia::render('management/backup-databases/Index', [
            'backups'      => $backups,
            'filters'      => ['search' => $search],
            'activeBackup' => $activeBackup,
        ]);
    }

    /**
     * Return current active backup status for polling.
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        $active = BackupDatabase::query()
            ->whereIn('status', [BackupDatabase::STATUS_PENDING, BackupDatabase::STATUS_PROCESSING])
            ->latest('id')
            ->first(['id', 'name', 'status', 'progress', 'progress_message', 'error_message', 'created_at']);

        $latest = BackupDatabase::query()
            ->latest('id')
            ->first(['id', 'name', 'status', 'progress', 'progress_message', 'error_message', 'created_at']);

        return response()->json([
            'active' => $active,
            'latest' => $latest,
        ]);
    }

    /**
     * Dispatch the database backup job.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $hasActive = BackupDatabase::query()
            ->whereIn('status', [BackupDatabase::STATUS_PENDING, BackupDatabase::STATUS_PROCESSING])
            ->exists();

        if ($hasActive) {
            return back()->with('error', 'Masih ada proses backup database yang sedang berjalan.');
        }

        $backup = BackupDatabase::create([
            'name'             => 'backup_' . now()->format('Y_m_d_His') . '.sql',
            'url'              => null,
            'status'           => BackupDatabase::STATUS_PENDING,
            'progress'         => 10,
            'progress_message' => 'Menunggu antrian worker...',
        ]);

        BackupDatabaseJob::dispatch($backup->id);

        return back()->with('success', 'Job Backup Database berhasil di-dispatch. Proses backup pgsql sedang berjalan di latar belakang.');
    }

    /**
     * Delete a database backup entry.
     */
    public function destroy(Request $request, BackupDatabase $backupDatabase): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        // Clean local public storage if exists
        $localPublicPath = 'backup_databases/' . $backupDatabase->name;
        if (Storage::disk('public')->exists($localPublicPath)) {
            Storage::disk('public')->delete($localPublicPath);
        }

        $backupDatabase->delete();

        return back()->with('success', 'Data backup database berhasil dihapus.');
    }

    /**
     * Download or redirect to the backup file.
     */
    public function download(Request $request, BackupDatabase $backupDatabase)
    {
        $this->authorizeSuperAdmin($request);

        if (!$backupDatabase->url || $backupDatabase->status !== BackupDatabase::STATUS_COMPLETED) {
            return back()->with('error', 'File backup belum tersedia untuk diunduh.');
        }

        if (config('services.supabase.url') && config('services.supabase.key')) {
            $signedUrl = S3Helper::getSignedUrl('backup_databases/' . $backupDatabase->name, 3600);
            return redirect()->away($signedUrl);
        }

        $localPublicPath = 'backup_databases/' . $backupDatabase->name;
        if (Storage::disk('public')->exists($localPublicPath)) {
            return Storage::disk('public')->download($localPublicPath, $backupDatabase->name);
        }

        return redirect()->away($backupDatabase->url);
    }
}
