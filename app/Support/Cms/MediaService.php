<?php

namespace App\Support\Cms;

use App\Models\MediaAsset;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Canonical service for the global media library (the existing
 * `media_library` table).
 *
 * - `upload(file, user)` stores the file via ImageProcessor, persists
 *   metadata, deduplicates by sha256 hash.
 * - `urlFor(asset)` builds the public URL from the disk + path.
 * - `delete(asset)` soft-deletes the row and removes the underlying file.
 *
 * Glide transforms (resize / WebP / srcset) arrive in a follow-up; today
 * blocks consume the raw stored URL.
 */
class MediaService
{
    public function __construct(
        private readonly ImageProcessor $images,
    ) {}

    public function upload(UploadedFile $file, ?int $userId = null): MediaAsset
    {
        $hash = hash_file('sha256', $file->getRealPath());

        return DB::transaction(function () use ($file, $userId, $hash) {
            // Dedup: if a non-deleted asset with the same hash already
            // exists, return it instead of re-storing.
            $existing = MediaAsset::query()->where('hash', $hash)->first();
            if ($existing !== null) {
                return $existing;
            }

            $disk = 'public';
            $path = $this->images->store($file, 'media');
            $dims = $this->dimensions($file);

            return MediaAsset::query()->create([
                'tenant_id' => null,
                'uploaded_by_id' => $userId,
                'disk' => $disk,
                'path' => $path,
                'filename' => $file->getClientOriginalName() ?: basename($path),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => (int) $file->getSize(),
                'width' => $dims['width'],
                'height' => $dims['height'],
                'hash' => $hash,
                'metadata' => [
                    'alt' => '',
                    'focal_x' => 0.5,
                    'focal_y' => 0.5,
                ],
            ]);
        });
    }

    public function urlFor(MediaAsset $asset): string
    {
        return Storage::disk($asset->disk)->url($asset->path);
    }

    public function delete(MediaAsset $asset): void
    {
        $path = $asset->path;
        $disk = $asset->disk;

        $asset->delete(); // soft-delete the row

        Storage::disk($disk)->delete($path);
    }

    /**
     * @return array{width: ?int, height: ?int}
     */
    private function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return ['width' => null, 'height' => null];
        }
        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }
}
