<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Handles file uploads and deletion. Uses the public disk by default (served via storage:link);
 * sensitive documents such as KTP photos use the private (local) disk.
 *
 * @author Charlene
 *
 * @version 1.0
 */
trait HandlesFileUpload
{
    /**
     * Store a new file and delete the old one if present.
     *
     * @param  UploadedFile  $file  Uploaded file from the request.
     * @param  string  $directory  Target folder inside the disk.
     * @param  string|null  $oldPath  Path of the old file being replaced.
     * @param  string  $disk  Disk name: public for publicly accessible files, local for private files.
     * @return string New file path relative to the disk.
     */
    protected function uploadFile(UploadedFile $file, string $directory, ?string $oldPath = null, string $disk = 'public'): string
    {
        $path = $file->store($directory, $disk);

        $this->deleteFile($oldPath, $disk);

        return $path;
    }

    protected function deleteFile(?string $path, string $disk = 'public'): void
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
