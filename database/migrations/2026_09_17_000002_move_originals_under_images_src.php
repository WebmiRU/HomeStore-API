<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('s3');

        foreach (DB::table('image')->whereNotNull('path')->get(['id', 'path']) as $image) {
            $newPath = $this->targetPath((string) $image->path);

            if ($newPath === $image->path) {
                continue;
            }

            if ($disk->exists($newPath)) {
                // already moved (shared bucket, другая БД успела) — просто обновляем путь
            } elseif ($disk->exists($image->path)) {
                $disk->move($image->path, $newPath);
            } else {
                continue;
            }

            DB::table('image')->where('id', $image->id)->update(['path' => $newPath]);
        }
    }

    public function down(): void
    {
        $disk = Storage::disk('s3');

        foreach (DB::table('image')->whereNotNull('path')->get(['id', 'path', 'sha256']) as $image) {
            $path = (string) $image->path;

            if (! str_starts_with($path, 'images/src/')) {
                continue;
            }

            $basename = basename($path);
            $oldPath = $image->sha256 === null ? 'images/' . $basename : 'src/' . $basename;

            if ($disk->exists($oldPath)) {
                // already reverted
            } elseif ($disk->exists($path)) {
                $disk->move($path, $oldPath);
            } else {
                continue;
            }

            DB::table('image')->where('id', $image->id)->update(['path' => $oldPath]);
        }
    }

    private function targetPath(string $path): string
    {
        if (str_starts_with($path, 'images/src/')) {
            return $path;
        }

        return 'images/src/' . basename($path);
    }
};
