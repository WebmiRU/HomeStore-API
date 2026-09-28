<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Размеры оригинала: ширина, высота и вес в байтах.
 *
 * Клиент отдаёт в srcset три варианта (1×, 1.5×, 2×) и режет список по
 * размеру оригинала: увеличивать картинку в браузере незачем, и вариант
 * «2×» для фотографии 300×200 — это всё равно 300×200, только раздутый вдвое.
 * Без размеров оригинала в ответе API браузер узнаёт об этом лишь после
 * загрузки — то есть уже заплатив за лишние байты.
 *
 * size нужен не только для справки: вкладка «Изображения» показывает вес
 * файла, и раньше она брала его оттуда, где его не было.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image', function ($table) {
            $table->integer('width')->nullable()->after('mime');
            $table->integer('height')->nullable()->after('width');
            $table->integer('size')->nullable()->after('height');
        });

        // Разовая дозагрузка: файлы уже лежат в хранилище, а размеры в базе
        // появились только сейчас. Читаем заголовок каждого — их десятки,
        // и это единственный способ узнать размер, не скачивая картинку
        // целиком дважды.
        $rows = DB::table('image')->select('id', 'path')->whereNull('width')->get();

        foreach ($rows as $row) {
            $bytes = \Illuminate\Support\Facades\Storage::disk('s3')->get($row->path);

            if ($bytes === null) {
                continue;
            }

            $info = @getimagesizefromstring($bytes);

            if ($info === false) {
                continue;
            }

            DB::table('image')->where('id', $row->id)->update([
                'width'  => $info[0],
                'height' => $info[1],
                'size'   => strlen($bytes),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('image', function ($table) {
            $table->dropColumn(['width', 'height', 'size']);
        });
    }
};
