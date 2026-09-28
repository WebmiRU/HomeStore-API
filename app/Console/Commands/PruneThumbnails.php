<?php

namespace App\Console\Commands;

use App\Models\Thumbnail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Удаляет файлы миниатюр, чьих размеров больше нет в каталоге.
 *
 * Файлы в хранилище производные: их можно построить заново из оригинала по
 * ключу из таблицы thumbnail. Поэтому каталог и файлы правятся отдельно — с
 *начала каталог миграцией, потом эта команда. Обратный порядок оставил бы
 * интерфейс без картинок: ключ есть в ссылке, а строки в базе уже нет, и
 * сервер отвечает 404, клиент уходит в оригинал целиком.
 *
 * Без --force только показывает, что было бы удалено.
 */
class PruneThumbnails extends Command
{
    protected $signature = 'thumbnails:prune {--force : Удалить файлы, а не только показать}';

    protected $description = 'Удаляет из хранилища файлы миниатюр, чьих размеров нет в каталоге';

    public function handle(): int
    {
        $disk = Storage::disk('s3');
        $known = Thumbnail::query()->pluck('key')->all();
        $known[] = 'originals';

        $counts = [];
        $bytes = 0;

        foreach ($disk->allFiles('images/thumbnails') as $file) {
            $parts = explode('/', $file);
            $key = $parts[2] ?? '';

            if (in_array($key, $known, true)) {
                continue;
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
            $bytes += $disk->size($file);
        }

        if ($counts === []) {
            $this->info('Лишних файлов нет.');

            return self::SUCCESS;
        }

        foreach ($counts as $key => $count) {
            $this->line(sprintf('  %-22s %4d файлов', $key, $count));
        }

        $this->info(sprintf('Всего: %d файлов, %s КБ', array_sum($counts), round($bytes / 1024)));

        if (! $this->option('force')) {
            $this->comment('Это список кандидатов. Удалить — thumbnails:prune --force');

            return self::SUCCESS;
        }

        foreach (array_keys($counts) as $key) {
            $disk->deleteDirectory("images/thumbnails/{$key}");
            $this->line("  удалён каталог images/thumbnails/{$key}");
        }

        $this->info('Готово.');

        return self::SUCCESS;
    }
}
