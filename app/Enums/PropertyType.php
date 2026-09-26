<?php

namespace App\Enums;

/**
 * Тип значения свойства предмета.
 *
 * Тип хранится строкой в property.type, а перечисление — единственное место,
 * где перечислены допустимые значения: валидация, нормализация значения и
 * подпись в интерфейсе берутся отсюда.
 *
 * Отличие от base220: там есть ещё interval (диапазон «от..до») и filter_mode
 * (как свойство показывается в фасетных фильтрах витрины). Витрины в
 * home_store нет, поэтому и диапазон, и режим фильтрации не перенесены.
 */
enum PropertyType: string
{
    case String = 'string';
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case Dictionary = 'dictionary';

    /**
     * Подпись типа в интерфейсе.
     */
    public function label(): string
    {
        return match ($this) {
            self::String => 'Текст',
            self::Int => 'Целое число',
            self::Float => 'Дробное число',
            self::Bool => 'Да/Нет',
            self::Dictionary => 'Из справочника',
        };
    }

    /**
     * Единица измерения осмысленна для всего, кроме справочника: у значения
     * из справочника единицу измерения не бывает.
     */
    public function usesUnit(): bool
    {
        return $this !== self::Dictionary;
    }

    /**
     * Приводит сырое значение из формы к каноническому виду.
     *
     * Приводит к строке, а не к числу: в БД значение лежит текстом, и
     * приводить его к типу имеет смысл только в момент фильтрации, которой
     * здесь нет. Канонизация нужна, чтобы «10», « 10 » и «+10» не считались
     * разными значениями одного свойства.
     *
     * @throws \InvalidArgumentException значение не соответствует типу или пусто
     */
    public function normalize(string $raw): string
    {
        // Неразрывный пробел приходит из автозамены на телефоне и из вставки
        // из Word, а для разбора числа ничем не отличается от обычного.
        $value = str_replace("\u{00A0}", ' ', $raw);
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Пустое значение');
        }

        return match ($this) {
            self::String => $value,
            self::Int => $this->normalizeInt($value),
            self::Float => $this->normalizeFloat($value),
            self::Bool => $this->normalizeBool($value),
            // Значение из справочника хранится в dictionary_value_id, а не
            // в текстовой колонке: писать в value тут нечего.
            self::Dictionary => throw new \InvalidArgumentException(
                'Свойство из справочника заполняется выбором значения'
            ),
        };
    }

    private function normalizeInt(string $value): string
    {
        $digits = str_replace(' ', '', $value);

        if (preg_match('/^[+-]?\d+$/', $digits) !== 1) {
            throw new \InvalidArgumentException("«{$value}» не целое число");
        }

        // Приводит «+7» и «007» к «7» — иначе сортировка и группировка
        // по значению разъезжаются.
        return (string) (int) $digits;
    }

    private function normalizeFloat(string $value): string
    {
        // В русской раскладке десятичный знак — запятая, и пользователь
        // вводит «0,75», не переключая раскладку.
        $number = str_replace([' ', ','], ['', '.'], $value);

        if (preg_match('/^[+-]?\d+(\.\d+)?$/', $number) !== 1) {
            throw new \InvalidArgumentException("«{$value}» не число");
        }

        return (string) (float) $number;
    }

    private function normalizeBool(string $value): string
    {
        return match (mb_strtolower($value)) {
            'да', 'yes', 'true', '1', 'on' => 'да',
            'нет', 'no', 'false', '0', 'off' => 'нет',
            default => throw new \InvalidArgumentException("«{$value}» не да/нет"),
        };
    }
}
