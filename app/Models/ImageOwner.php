<?php

namespace App\Models;

/**
 * Сущность, у которой есть фотографии: предмет, хранилище, склад.
 *
 * Тип живёт отдельно, чтобы контроллер изображений не перечислял союз
 * восемь раз: при добавлении склада хватило дописать его в одну строку.
 */
interface ImageOwner
{
    public function images(): \Illuminate\Database\Eloquent\Relations\BelongsToMany;
}
