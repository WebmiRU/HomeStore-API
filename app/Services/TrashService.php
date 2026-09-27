<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Dictionary;
use App\Models\DictionaryValue;
use App\Models\Item;
use App\Models\LabelList;
use App\Models\LabelPreset;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\Store;
use App\Models\Unit;
use App\Models\UserProfile;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Корзина: удалённые записи, восстановление и окончательное удаление.
 *
 * Что происходит в корзине:
 *
 *   - список отдаёт записи, помеченные deleted_at, и только те, что
 *     принадлежат текущему пользователю. Чужие записи, доступные по правам на
 *     склад, показывать нельзя: восстанавливать и удалять их не даст, а
 *     показ вводил бы в заблуждение, будто это можно.
 *   - восстановление возвращает запись как есть; если имя уже занято другой
 *     записью, восстановление отказывает, а не переименовывает молча: тихо
 *     испорченное имя хуже понятной ошибки.
 *   - окончательное удаление необратимо. Каскады при нём прежние: удалил
 *     категорию — ушла её ветвь, удалил склад — ушли его хранилища. Люди,
 *     предметы и склады, на которые удалённое ссылалось, уцелеют: их ссылки
 *     обнуляет nullOnDelete.
 *
 * Пользователей окончательно удалять нельзя (свойства FK каскадные, и такая
 * механика слишком опасна), поэтому в корзине у них есть только
 * восстановление.
 */
class TrashService
{
    /**
     * Разделы корзины: ключ в URL => модель.
     *
     * Ключ в URL — то же имя, что и в префиксе обычных маршрутов, чтобы
     * «удалённый предмет» читался как /trash/item, а не как /trash/items.
     *
     * @return array<string, class-string<Model>>
     */
    public const SECTIONS = [
        'warehouse'      => Warehouse::class,
        'store'          => Store::class,
        'item'           => Item::class,
        'category'       => Category::class,
        'vendor'         => Vendor::class,
        'unit'           => Unit::class,
        'property'       => Property::class,
        'property-group' => PropertyGroup::class,
        'dictionary'     => Dictionary::class,
        'dictionary-value' => DictionaryValue::class,
        'label-preset'   => LabelPreset::class,
        'label-list'     => LabelList::class,
        'user'           => UserProfile::class,
    ];

    /** Разделы, где окончательное удаление запрещено. */
    private const NO_PURGE = ['user'];

    public function modelFor(string $section): string
    {
        abort_unless(array_key_exists($section, self::SECTIONS), 404, "Неизвестный раздел корзины: {$section}");

        return self::SECTIONS[$section];
    }

    public function purgeAllowed(string $section): bool
    {
        return ! in_array($section, self::NO_PURGE, true);
    }

    /**
     * Удалённые записи раздела, по 50 на страницу.
     *
     * Владелец — всегда текущий пользователь, а не «доступные по складу»:
     * скоуп доступности у предметов и хранилищ шире владения, и без этой
     * проверки в корзине оказались бы чужие записи, которыми нельзя
     * распорядиться.
     */
    public function listing(string $section, int $page)
    {
        /** @var Model $model */
        $model = $this->modelFor($section);

        return $model::onlyTrashed()
            ->where('user_id', CurrentUser::id())
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'page', $page);
    }

    /**
     * Восстанавливает записи по списку id.
     *
     * @param  int[]  $ids
     * @return array{restored: int, failed: array<string, string>}
     */
    public function restore(string $section, array $ids): array
    {
        /** @var Model $model */
        $model = $this->modelFor($section);

        $restored = 0;
        $failed = [];

        foreach ($ids as $id) {
            $record = $model::onlyTrashed()
                ->where('user_id', CurrentUser::id())
                ->find($id);

            if ($record === null) {
                continue;
            }

            try {
                $record->restore();
                $restored++;
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                // Частичный уникальный индекс не даёт вернуть запись, если
                // такое имя уже занято живой. Молча переименовывать нельзя:
                // человек потом не найдёт своё переименованное значение.
                $failed[$this->titleOf($record)] = 'Название уже занято другой записью';
            }
        }

        return ['restored' => $restored, 'failed' => $failed];
    }

    /**
     * Окончательное удаление по списку id.
     *
     * @param  int[]  $ids
     * @return array{purged: int, titles: string[]}
     */
    public function purge(string $section, array $ids): array
    {
        abort_unless($this->purgeAllowed($section), 403, 'Эти записи нельзя удалить окончательно');

        /** @var Model $model */
        $model = $this->modelFor($section);

        $purged = 0;
        $titles = [];

        foreach ($ids as $id) {
            $record = $model::onlyTrashed()
                ->where('user_id', CurrentUser::id())
                ->find($id);

            if ($record === null) {
                continue;
            }

            $titles[] = $this->titleOf($record);
            $record->forceDelete();
            $purged++;
        }

        return ['purged' => $purged, 'titles' => $titles];
    }

    /**
     * Заголовок записи для корзины.
     *
     * У разных сущностей он называется по-разному, а у единицы измерения их
     * два; если ничего не нашлось, показываем id — чтобы в списке корзины всё
     * равно было видно, что за строка.
     */
    public function titleOf(Model $record): string
    {
        if (isset($record->title_full) && $record->title_full !== null) {
            return $record->title_short . ' — ' . $record->title_full;
        }

        if (isset($record->title) && $record->title !== null) {
            return (string) $record->title;
        }

        return '#' . $record->getKey();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505' || (int) ($e->errorInfo[0] ?? 0) === 23505;
    }
}
