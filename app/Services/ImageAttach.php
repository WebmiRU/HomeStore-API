<?php

namespace App\Services;

use App\Models\Image;
use App\Models\ImageOwner;
use Illuminate\Validation\ValidationException;

/**
 * Привязка фотографий к сущности.
 *
 * Отдельный сервис, а не метод контроллера изображений: привязка нужна при
 * создании и правке предмета, склада и хранилища, и внедрять туда ещё и
 * контроллер — значит тянуть в конструктор зависимость от слоя выше.
 *
 * Фотографии можно загрузить раньше, чем появилась сущность: пока её нет,
 * привязать не к чему, и файл всё равно уходит на диск. Такие картинки ждут
 * своего владельца в поле images при сохранении, а если человек закрыл форму
 * не сохранив, остаются ни с чем — их убирают при обслуживании.
 *
 * Владельца у картинки нет: файлы общие и дедуплицируются по содержимому,
 * поэтому «только что загруженная» и «та же самая, загруженная месяц назад» —
 * одна и та же строка.
 */
class ImageAttach
{
    /**
     * Привязать перечисленные картинки к сущности.
     *
     * @param  array<int, array{id: mixed, alt?: mixed}>  $rows
     *
     * @throws ValidationException
     */
    public function attachTo(ImageOwner $model, array $rows): void
    {
        foreach ($rows as $row) {
            $imageId = (int) ($row['id'] ?? 0);
            $image = Image::find($imageId);

            if ($image === null) {
                throw ValidationException::withMessages([
                    'images' => [sprintf(__('Картинка с id %d не найдена'), $imageId)],
                ]);
            }

            // Одна и та же картинка дважды в списке дала бы две строки в
            // таблице связей на одну картинку, и в галерее она бы показалась
            // дважды — по разу на каждое место.
            if ($model->images()->where('image.id', $image->id)->exists()) {
                continue;
            }

            $model->images()->attach($image->id, [
                'alt'    => $row['alt'] ?? null,
                'weight' => $this->nextWeight($model),
            ]);
        }
    }

    /**
     * Порядковый номер следующей картинки: текущий максимум плюс один.
     *
     * Считается по таблице связей, а не по числу картинок: сортировка живёт в
     * весе, и после перестановки он не обязательно равен количеству.
     */
    public function nextWeight(ImageOwner $model): int
    {
        [, $key] = $this->pivotFor($model);

        return (int) $model->images()->max('image_m2m_' . rtrim($key, '_id') . '.weight') + 1;
    }

    /**
     * Таблица связей с картинками и её ключ.
     *
     * Список здесь, а не троичные условия по instanceof: сущностей с
     * фотографиями стало три, и каждое новое место требовало бы править ещё
     * четыре места в контроллере.
     *
     * @return array{0: string, 1: string}
     */
    public function pivotFor(ImageOwner $model): array
    {
        return match (true) {
            $model instanceof \App\Models\Item      => ['image_m2m_item', 'item_id'],
            $model instanceof \App\Models\Store     => ['image_m2m_store', 'store_id'],
            $model instanceof \App\Models\Warehouse => ['image_m2m_warehouse', 'warehouse_id'],
            $model instanceof \App\Models\Category  => ['image_m2m_category', 'category_id'],
            /*
             * Без ветки «по умолчанию»: новая сущность с фотографиями молча
             * уехала бы в связь склада, и загрузка падала бы с «нет таблицы
             * image_m2m_warehouse» — ошибка в чужом месте и не о том, что
             * забыли добавить строку здесь.
             */
            default => throw new \InvalidArgumentException(
                'Не задана связь изображений для ' . $model::class
            ),
        };
    }
}
