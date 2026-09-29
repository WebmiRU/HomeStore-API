<?php

namespace App\Models;

use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Model;

/**
 * Значение свойства на предмете. Своего user_id нет: строка достижима
 * только через предмет, а владение предметом уже отсечено скоупом.
 *
 * Исходный ввод лежит в value и остаётся таким, каким его ввели. Рядом
 * база сама считает приведённые значения по типам — value_int, value_float,
 * value_bool, value_text, — и значение читается по колонке типа свойства.
 */
class ItemProperty extends Model
{
    protected $table = 'item_property';

    protected $fillable = [
        'item_id',
        'property_id',
        'value',
        'dictionary_value_id',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort'        => 'integer',
            'value_int'   => 'integer',
            'value_float' => 'float',
            'value_bool'  => 'boolean',
        ];
    }

    /**
     * Значение в виде, пригодном для типа свойства.
     *
     * Исходный ввод лежит в value, а приведённый по типам считают
     * generated-колонки: целое в value_int, дробное в value_float, «да/нет»
     * в value_bool, текст в value_text. Читается та колонка, которая
     * соответствует типу свойства, поэтому смена типа у заполненного
     * свойства не требует ничего пересчитывать — достаточно поменять type.
     *
     * Значение, которое в новый тип не помещается («Самая прочная» в целом),
     * приходит пустым: колонка строгая и молча ставить ноль вместо текста
     * не должна. Исходный ввод при этом никуда не делся, поэтому смена типа
     * обратима.
     */
    public function typedValue(): ?string
    {
        if ($this->property === null) {
            return $this->value;
        }

        return match ($this->property->type) {
            PropertyType::Int   => $this->value_int === null ? null : (string) $this->value_int,
            PropertyType::Float => $this->value_float === null ? null : $this->floatText(),
            // «Да» и «нет» пишутся по-русски, и колонка хранит признак.
            PropertyType::Bool  => $this->value_bool === null ? null : ($this->value_bool ? 'да' : 'нет'),
            PropertyType::String => $this->value_text,
            PropertyType::Dictionary => null,
        };
    }

    /**
     * Дробное без хвостовых нулей: 1.5, а не 1.5000000001 или 1.500000.
     *
     * double хранит значение приближённо, и печать полной точности выдала бы
     * пользователю то, чего он не вводил.
     */
    private function floatText(): string
    {
        $text = rtrim(rtrim(sprintf('%.10F', $this->value_float), '0'), '.');

        return $text === '' || $text === '-' ? '0' : $text;
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function dictionaryValue()
    {
        return $this->belongsTo(DictionaryValue::class, 'dictionary_value_id');
    }
}
