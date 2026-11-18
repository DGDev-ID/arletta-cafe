<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class S3Helper
{
    protected static function baseUrl(): string
    {
        return config('services.supabase.url') ? config('services.supabase.url') . '/storage/v1' : '';
    }

    protected static function apiKey(): string
    {
        return (string) config('services.supabase.key');
    }

    protected static function bucket(): string
    {
        return (string) config('services.supabase.bucket');
    }

    /*
    |--------------------------------------------------------------------------
    | TEMP STORAGE (LOCAL)
    |--------------------------------------------------------------------------
    */

    public static function storeFileTemp(UploadedFile $file): string
    {
        $uuid = (string) Str::uuid();
        $mime = $file->getMimeType();

        $isImage = str_starts_with($mime, 'image/');

        $extension = $file->getClientOriginalExtension();

        if (!$isImage) {
            $fileName = "{$uuid}.{$extension}";
            Storage::disk('local')->putFileAs('temp', $file, $fileName);

            return $fileName;
        }

        $fileName = "{$uuid}.webp";
        $tempPath = storage_path("app/temp/{$fileName}");

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($file->getRealPath());
                break;

            case 'image/png':
                $image = @imagecreatefrompng($file->getRealPath());
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;

            case 'image/gif':
                $image = imagecreatefromgif($file->getRealPath());
                break;

            case 'image/webp':
                Storage::disk('local')->putFileAs('temp', $file, $fileName);
                return $fileName;

            default:
                $fileName = "{$uuid}.{$extension}";
                Storage::disk('local')->putFileAs('temp', $file, $fileName);
                return $fileName;
        }

        imagewebp($image, $tempPath, 80);
        imagedestroy($image);

        Storage::disk('local')->put(
            "temp/{$fileName}",
            file_get_contents($tempPath)
        );

        unlink($tempPath);
        return $fileName;
    }

    public static function getFileTemp(string $fileName): ?string
    {
        $path = "temp/{$fileName}";

        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        return Storage::disk('local')->get($path);
    }

    public static function removeFileTemp(string $fileName): bool
    {
        $path = "temp/{$fileName}";

        return Storage::disk('local')->exists($path)
            ? Storage::disk('local')->delete($path)
            : false;
    }

    /*
    |--------------------------------------------------------------------------
    | SUPABASE STORAGE
    |--------------------------------------------------------------------------
    */

    public static function storeFileToS3(string $path, string $fileName): string
    {
        $localPath = "temp/{$fileName}";

        if (!Storage::disk('local')->exists($localPath)) {
            throw new \Exception("Temp file not found: {$fileName}");
        }

        $fileContent = Storage::disk('local')->get($localPath);

        $supabasePath = trim($path, '/') . '/' . $fileName;

        if (!config('services.supabase.url') || !config('services.supabase.key')) {
            // Fallback to local public storage
            Storage::disk('public')->put($supabasePath, $fileContent);
            return $supabasePath;
        }

        $response = Http::withHeaders([
            'apikey'        => self::apiKey(),
            'Authorization' => 'Bearer ' . self::apiKey(),
        ])->attach(
            'file',
            $fileContent,
            $fileName
        )->post(self::baseUrl() . "/object/" . self::bucket() . "/" . $supabasePath);

        if (!$response->successful()) {
            throw new \Exception("Upload failed: " . $response->body());
        }

        return $supabasePath;
    }

    public static function getUrlFileS3(string $path, string $fileName): string
    {
        $supabasePath = trim($path, '/') . '/' . $fileName;

        if (!config('services.supabase.url') || !config('services.supabase.key')) {
            return Storage::disk('public')->url($supabasePath);
        }

        return config('services.supabase.url') .
            "/storage/v1/object/public/" .
            self::bucket() . "/" . $supabasePath;
    }

    /**
     * Generate a Supabase signed URL for a file (works for private buckets).
     * Falls back to public URL if Supabase is not configured.
     */
    public static function getSignedUrl(string $storagePath, int $expiresIn = 3600): string
    {
        if (!config('services.supabase.url') || !config('services.supabase.key')) {
            return Storage::disk('public')->url($storagePath);
        }

        $response = Http::withHeaders([
            'apikey'        => self::apiKey(),
            'Authorization' => 'Bearer ' . self::apiKey(),
            'Content-Type'  => 'application/json',
        ])->post(
            self::baseUrl() . '/object/sign/' . self::bucket() . '/' . ltrim($storagePath, '/'),
            ['expiresIn' => $expiresIn]
        );

        if ($response->successful()) {
            $signedUrl = $response->json('signedURL') ?? $response->json('signedUrl');
            if ($signedUrl) {
                if (str_starts_with($signedUrl, '/')) {
                    return config('services.supabase.url') . '/storage/v1' . $signedUrl;
                }
                return $signedUrl;
            }
        }

        return config('services.supabase.url') .
            "/storage/v1/object/public/" .
            self::bucket() . "/" . ltrim($storagePath, '/');
    }

    public static function downloadToTemp(string $source): string
    {
        $tempDir = storage_path('app/temp');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 🔹 Jika URL langsung
        if (filter_var($source, FILTER_VALIDATE_URL)) {

            $response = Http::get($source);

            if (!$response->successful()) {
                throw new \Exception("Failed to download file from URL: {$source}");
            }

            $extension = pathinfo(
                parse_url($source, PHP_URL_PATH),
                PATHINFO_EXTENSION
            );

            $tempFileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');

            file_put_contents(
                "{$tempDir}/{$tempFileName}",
                $response->body()
            );

            return $tempFileName;
        }

        // 🔹 Jika dari Supabase Storage
        $fileUrl = config('services.supabase.url') .
            "/storage/v1/object/public/" .
            self::bucket() . "/" . $source;

        $response = Http::get($fileUrl);

        if (!$response->successful()) {
            throw new \Exception("File not found in Supabase: {$source}");
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $tempFileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');

        file_put_contents(
            "{$tempDir}/{$tempFileName}",
            $response->body()
        );

        return $tempFileName;
    }
}
