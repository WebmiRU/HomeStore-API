<?php

namespace App\Services;

use App\Enums\PropertyType;
use App\Models\Category;
use App\Models\DictionaryValue;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

/**
 * Значения свойств предмета: вычисление набора для категории, проверка
 * присланного и запись на предмет.
 */
class ItemPropertyService
{
    /**
     * Набор свойств, которые имеет смысл предложить заполнить для категории.
     *
     * Считается по уже заполненным значениям — так же, как в base220, и
     * ровно по тем же причинам: у новой категории, где предметов ещё нет,
     * набора нет, и он появляется сам, как только кто-то что-нибудь заполнит.
     * Хранить связь «свойство — категория» значило бы поддерживать её руками
     * и получать расхождение с тем, чем предметы реально заполнены.
     *
     * В отличие от base220, учитываются и предметы вложенных категорий: там
     * считались только прямые, из-за чего набор у родительской категории
     * оказывался пустым, пока в её ветвях не лежал собственный предмет.
     *
     * @return Collection<int, Property>
     */
    public function forCategory(?Category $category): Collection
    {
        if ($category === null) {
            return new Collection();
        }

        $categoryIds = array_merge([$category->id], $category->descendantIds());

        $itemIds = Item::query()
            // Своё условие обязано быть в скобках: скоуп AccessibleByUser
            // добавляет orWhere, а приписанный после него and без скобок
            // переписал бы смысл на «предметы, доступные по складу,
            // подходят всегда», и из выборки выпала бы проверка категории.
            ->where(fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->pluck('id');

        if ($itemIds->isEmpty()) {
            return new Collection();
        }

        $propertyIds = ItemProperty::query()
            ->whereIn('item_id', $itemIds)
            ->distinct()
            ->pluck('property_id');

        if ($propertyIds->isEmpty()) {
            return new Collection();
        }

        return Property::query()
            ->with(['group', 'unit', 'dictionary'])
            ->whereIn('id', $propertyIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * Приводит присланные из формы значения к каноническому виду и проверяет,
     * что они годятся для своих свойств.
     *
     * Проверки здесь, а не в правилах FormRequest, по одной причине: тип и
     * справочник свойства лежат в БД, а правила валидации не умеют смотреть
     * в другие строки. Плюс нормализация («+7» → «7», «1,50» → «1.5»,
     * «да» → «да») — это не проверка, а приведение, и правила для неё тоже
     * не годятся.
     *
     * Пустые значения не ошибка: форма шлёт все поля раздела, и незаполненное
     * просто не попадает в результат.
     *
     * @param  array<int, array{property_id?: mixed, values?: array}>  $rows
     * @return array<int, array{property_id: int, value: ?string, dictionary_value_id: ?int, sort: int}>
     *
     * @throws ValidationException
     */
    public function normalize(array $rows): array
    {
        $propertyIds = collect($rows)
            ->pluck('property_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $properties = Property::query()
            ->whereIn('id', $propertyIds)
            ->get()
            ->keyBy('id');

        $valueOwners = $this->dictionaryValueOwners($rows);

        $normalized = [];
        $errors = [];
        $sorts = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $key = "properties.{$index}";

            $propertyId = (int) ($row['property_id'] ?? 0);
            $property = $properties->get($propertyId);

            if ($property === null) {
                $errors[$key] = 'Свойство не найдено';
                continue;
            }

            foreach ($row['values'] ?? [] as $valueIndex => $raw) {
                $rowKey = "{$key}.values.{$valueIndex}";
                $sort = $sorts[$propertyId] ?? 0;

                $value = $this->normalizeValue(
                    $property,
                    is_array($raw) ? $raw : [],
                    $valueOwners,
                    $rowKey,
                    $errors,
                );

                if ($value === null) {
                    continue;
                }

                // Точный дубль отбрасываем: форма шлёт по строке на значение,
                // и повтор означал бы двойной клик по «+», а не два разных
                // значения. Разные значения того же свойства остаются.
                $fingerprint = $value['dictionary_value_id'] !== null
                    ? 'd' . $value['dictionary_value_id']
                    : 'v' . $value['value'];

                if (isset($seen[$propertyId][$fingerprint])) {
                    continue;
                }

                $seen[$propertyId][$fingerprint] = true;
                $sorts[$propertyId] = $sort + 1;

                $normalized[] = [
                    'property_id' => $propertyId,
                    'value' => $value['value'],
                    'dictionary_value_id' => $value['dictionary_value_id'],
                    'sort' => $sort,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * Перезаписывает значения свойств предмета.
     *
     * Именно перезапись, а не дописывание: по API нельзя отличить «пользователь
     * очистил поле» от «пользователь не прислал это поле», а форма шлёт
     * раздел целиком. Слияние оставило бы удалённое значение жить вечно.
     */
    public function sync(Item $item, array $normalized): void
    {
        $item->propertyValues()->delete();

        foreach ($normalized as $row) {
            $item->propertyValues()->create($row);
        }
    }

    /**
     * Словарь «id значения справочника → id справочника», по которому видно,
     * что значение взято из того справочника, который назначен свойству.
     *
     * Ищутся именно присланные значения, а не все значения справочников
     * свойств: справочник, к которому принадлежит значение, в этом списке
     * может и не быть, и без точной выборки значение из чужого справочника
     * не отличилось бы от несуществующего — оба случая дали бы одну и ту же
     * ошибку, а человеку нужен разный ответ на «нет такого значения» и
     * «взято не из того списка».
     *
     * @param  array<int, array{property_id?: mixed, values?: array}>  $rows
     * @return SupportCollection<int, int>
     */
    private function dictionaryValueOwners(array $rows): SupportCollection
    {
        $valueIds = collect($rows)
            ->flatMap(fn (array $row) => $row['values'] ?? [])
            ->pluck('dictionary_value_id')
            ->filter()
            ->unique()
            ->values();

        if ($valueIds->isEmpty()) {
            return collect();
        }

        return DictionaryValue::query()
            ->whereIn('id', $valueIds)
            ->pluck('dictionary_id', 'id');
    }

    /**
     * @param  array<string, string>  $errors  ключ правила => сообщение
     * @return ?array{value: ?string, dictionary_value_id: ?int}
     */
    private function normalizeValue(
        Property $property,
        array $raw,
        SupportCollection $valueOwners,
        string $key,
        array &$errors,
    ): ?array {
        if ($property->type === PropertyType::Dictionary) {
            $valueId = $raw['dictionary_value_id'] ?? null;

            if ($valueId === null) {
                return null;
            }

            $ownerDictionaryId = $valueOwners->get((int) $valueId);

            if ($ownerDictionaryId === null) {
                $errors["{$key}.dictionary_value_id"] = 'Значение не найдено в справочнике';

                return null;
            }

            if ((int) $ownerDictionaryId !== (int) $property->dictionary_id) {
                $errors["{$key}.dictionary_value_id"] = 'Значение принадлежит другому справочнику';

                return null;
            }

            return ['value' => null, 'dictionary_value_id' => (int) $valueId];
        }

        $rawValue = $raw['value'] ?? null;

        if (! is_string($rawValue) || trim($rawValue) === '') {
            return null;
        }

        try {
            $value = $property->type->normalize($rawValue);
        } catch (\InvalidArgumentException $exception) {
            $errors["{$key}.value"] = $exception->getMessage();

            return null;
        }

        return ['value' => $value, 'dictionary_value_id' => null];
    }
}
