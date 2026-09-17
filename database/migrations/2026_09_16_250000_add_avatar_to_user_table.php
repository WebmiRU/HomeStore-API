<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user', 'avatar_id')) {
            Schema::table('user', function (Blueprint $table) {
                $table->unsignedBigInteger('avatar_id')->nullable()->after('password');
                $table->foreign('avatar_id')
                    ->references('id')
                    ->on('image')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (! Schema::hasColumn('user', 'avatar')) {
            return;
        }

        $now = now();
        $legacyPaths = [];

        $users = DB::table('user')->whereNotNull('avatar')->get(['id', 'avatar']);

        foreach ($users as $user) {
            $avatar = (string) $user->avatar;
            $sha256 = pathinfo($avatar, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($avatar, PATHINFO_EXTENSION)) ?: 'bin';
            $mime = $this->mime($extension);

            $image = DB::table('image')->where('sha256', $sha256)->first();

            if ($image === null) {
                $path = 'src/' . $sha256 . '.' . $extension;
                $contents = Storage::disk('s3')->get($avatar);

                if ($contents !== null) {
                    Storage::disk('s3')->put($path, $contents, ['ContentType' => $mime]);
                }

                $imageId = DB::table('image')->insertGetId([
                    'path'          => $path,
                    'original_name' => basename($avatar),
                    'mime'          => $mime,
                    'sha256'        => $sha256,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            } else {
                $imageId = $image->id;
            }

            DB::table('user')->where('id', $user->id)->update(['avatar_id' => $imageId]);
            $legacyPaths[] = $avatar;
        }

        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });

        if ($legacyPaths !== []) {
            DB::afterCommit(function () use ($legacyPaths): void {
                Storage::disk('s3')->delete($legacyPaths);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user', 'avatar')) {
            Schema::table('user', function (Blueprint $table) {
                $table->string('avatar')->nullable()->after('password');
            });
        }

        if (! Schema::hasColumn('user', 'avatar_id')) {
            return;
        }

        foreach (DB::table('user')->whereNotNull('avatar_id')->get(['id', 'avatar_id']) as $user) {
            $path = DB::table('image')->where('id', $user->avatar_id)->value('path');

            if ($path !== null) {
                DB::table('user')->where('id', $user->id)->update(['avatar' => $path]);
            }
        }

        Schema::table('user', function (Blueprint $table) {
            $table->dropForeign(['avatar_id']);
            $table->dropColumn('avatar_id');
        });
    }

    private function mime(string $extension): string
    {
        return match ($extension) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => 'image/jpeg',
        };
    }
};
