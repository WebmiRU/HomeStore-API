<?php

namespace App\Services;

use App\Enums\PropertyType;
use App\Models\ItemProperty;
use App\Models\Property;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Смена типа свойства вместе с его значениями.
 *
 * Значение у предмета лежит одной текстовой колонкой, и тип решает только то,
 * как этот текст читается. Значит, смена типа — это не «переложить данные в
 * другую колонку», а пересчёт каждого значения в новый вид: «007» станет «7»,
 * «1,50» — «1.5», «да» — «нет»-значением по правилам нового типа.
 *
 * Пересчёт не всегда возможен, и тогда смена отменяется целиком с внятным
 * объяснением — молча очистить чужие значения хуже, чем отказать.
 */
class PropertyTypeChange
{
    /**
     * Пересчитывает значения свойства под новый тип и меняет сам тип.
     *
     * Всё или ничего: если хоть одно значение в новый тип не помещается,
     * не меняется ничего — иначе свойство осталось бы с новым типом и
     * старыми, уже негодными значениями.
     */
    public function apply(Property $property, PropertyType $type): void
    {
        $values = $property->values()->get();

        if ($values->isNotEmpty()) {
            $this->ensureConvertible($property, $type, $values);
        }

        DB::transaction(function () use ($property, $type, $values): void {
            foreach ($values as $value) {
                if ($value->value === null || $value->value === '') {
                    // Пустое значение остаётся пустым: пересчитывать нечего,
                    // и новый тип не требует его заполнять.
                    continue;
                }

                $value->value = $type->normalize($value->value);
                $value->save();
            }

            $property->type = $type;

            // Справочник и единица привязаны к типу: сменился тип — привязка
            // могла остаться от прежнего и мешала бы.
            if (! $type->usesUnit()) {
                $property->unit_id = null;
            }

            if ($type !== PropertyType::Dictionary) {
                $property->dictionary_id = null;
            }

            $property->save();
        });
    }

    /**
     * Проверяет, что значения переживут смену типа, и объясняет, если нет.
     *
     * @param  \Illuminate\Support\Collection<int, ItemProperty>  $values
     */
    private function ensureConvertible(Property $property, PropertyType $type, $values): void
    {
        $isDictionary = $property->type === PropertyType::Dictionary;

        if ($isDictionary) {
            // Значение из справочника лежит в dictionary_value_id, а в
            // текстовой колонке пусто: пересчитывать нечего, и после смены
            // типа значения просто исчезли бы.
            abort(422, __('Нельзя сменить тип: значения выбраны из справочника, снимите их у предметов.'));
        }

        if ($type === PropertyType::Dictionary) {
            // Обратное направление: текстовое значение не станет выбором из
            // справочника, и предметы останутся с незаполненным свойством.
            abort(422, __('Нельзя сменить тип на «Из справочника»: сначала снимите значения у предметов.'));
        }

        $wrong = [];

        foreach ($values as $value) {
            if ($value->value === null || $value->value === '') {
                continue;
            }

            try {
                $type->normalize($value->value);
            } catch (InvalidArgumentException) {
                $wrong[$value->value] = true;
            }
        }

        if ($wrong === []) {
            return;
        }

        $shown = array_slice(array_keys($wrong), 0, 3);
        $rest = count($wrong) - count($shown);
        $list = implode(', ', array_map(fn (string $value): string => '«' . $value . '»', $shown))
            . ($rest > 0 ? __(' и ещё :count', ['count' => $rest]) : '');

        abort(422, __('Нельзя сменить тип: новый тип не подходит значениям :list', ['list' => $list]));
    }
}
