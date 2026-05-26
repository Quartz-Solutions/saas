<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the filesystem for storing user-uploaded images.
 *
 * Intentionally framework-only (no Intervention\Image dependency yet). The
 * surface is small enough that controllers can depend on this seam without
 * caring about future resize / optimisation work.
 */
class ImageProcessor
{
    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Persist an uploaded image, return the storage-relative path.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $filename = Str::random(40).'.'.$extension;
        $path = trim($directory, '/').'/'.$filename;

        Storage::disk($this->disk)->putFileAs(
            trim($directory, '/'),
            $file,
            $filename,
        );

        return $path;
    }

    /**
     * Delete a previously stored image (no-op if it doesn't exist).
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk($this->disk)->delete($path);
    }
}
