<?php

use App\Helpers\S3Helper;
use App\Jobs\BackupDatabase as BackupDatabaseJob;
use App\Models\BackupDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Super Admin']);
    Role::firstOrCreate(['name' => 'Admin']);
});

test('guests are redirected to login when accessing backup databases', function () {
    $this->get(route('management.backup-databases.index'))
        ->assertRedirect('/login');
});

test('non super admin users receive 403 on backup database routes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $backup = BackupDatabase::create([
        'name' => 'backup_test_2026_11_18_120000.sql',
        'url' => 'https://example.com/backup_test.sql',
    ]);

    $this->actingAs($admin);

    $this->get(route('management.backup-databases.index'))->assertForbidden();
    $this->post(route('management.backup-databases.store'))->assertForbidden();
    $this->delete(route('management.backup-databases.destroy', $backup))->assertForbidden();
    $this->get(route('management.backup-databases.download', $backup))->assertForbidden();
});

test('super admin can view backup databases list with search filter', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    BackupDatabase::create([
        'name' => 'backup_arletta_cafe_2026_11_18_100000.sql',
        'url' => 'https://example.com/1.sql',
    ]);
    BackupDatabase::create([
        'name' => 'backup_other_2026_11_18_110000.sql',
        'url' => 'https://example.com/2.sql',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('management.backup-databases.index', ['search' => 'arletta_cafe']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('management/backup-databases/Index')
            ->where('auth.is_superadmin', true)
            ->has('backups.data', 1)
            ->where('backups.data.0.name', 'backup_arletta_cafe_2026_11_18_100000.sql')
        );
});

test('super admin can dispatch backup database job', function () {
    Bus::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $this->actingAs($superAdmin)
        ->post(route('management.backup-databases.store'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Bus::assertDispatched(BackupDatabaseJob::class);
});

test('super admin can delete backup database record and local public file', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $fileName = 'backup_arletta_cafe_2026_11_18_120000.sql';
    Storage::disk('public')->put('backup_databases/' . $fileName, '-- sql dump');

    $backup = BackupDatabase::create([
        'name' => $fileName,
        'url' => Storage::disk('public')->url('backup_databases/' . $fileName),
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('management.backup-databases.destroy', $backup))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(BackupDatabase::find($backup->id))->toBeNull();
    Storage::disk('public')->assertMissing('backup_databases/' . $fileName);
});

test('super admin can download backup database from local public disk when supabase is unconfigured', function () {
    Storage::fake('public');
    config(['services.supabase.url' => null, 'services.supabase.key' => null]);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $fileName = 'backup_arletta_cafe_2026_11_18_120000.sql';
    Storage::disk('public')->put('backup_databases/' . $fileName, '-- sql dump');

    $backup = BackupDatabase::create([
        'name' => $fileName,
        'url' => '/storage/backup_databases/' . $fileName,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('management.backup-databases.download', $backup))
        ->assertOk()
        ->assertDownload($fileName);
});

test('backup database job executes pg_dump, uploads via S3Helper fallback, and records backup', function () {
    Storage::fake('local');
    Storage::fake('public');
    config([
        'services.supabase.url' => null,
        'services.supabase.key' => null,
        'database.connections.sqlite.database' => 'arletta_cafe',
    ]);

    Process::fake(function ($pendingProcess) {
        $command = $pendingProcess->command;
        $fIndex = array_search('-f', $command, true);
        if ($fIndex !== false && isset($command[$fIndex + 1])) {
            $outputPath = $command[$fIndex + 1];
            @mkdir(dirname($outputPath), 0755, true);
            file_put_contents($outputPath, "-- PostgreSQL database dump\nSELECT 1;");
        }

        return Process::result(output: '');
    });

    (new BackupDatabaseJob())->handle();

    expect(BackupDatabase::count())->toBe(1);
    $record = BackupDatabase::first();
    expect($record->name)->toStartWith('backup_arletta_cafe_')
        ->and($record->name)->toEndWith('.sql');

    Storage::disk('public')->assertExists('backup_databases/' . $record->name);
    expect(Storage::disk('local')->files('temp'))->toBeEmpty();
});
