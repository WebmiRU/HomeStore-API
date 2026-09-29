<?php

namespace Tests\Unit;

use App\Enums\PropertyType;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Приведение значения к типу свойства.
 *
 * Это то, чем смена типа пересчитывает уже заполненные значения: значение
 * лежит текстом, а тип решает, как этот текст читается. Поэтому тест держит
 * два свойства, на которых держится смена типа.
 *
 * 1. Пересчёт точен: «007» становится «7», «1,50» — «1.5», «да» — «да».
 *    Иначе после смены типа значения перестали бы совпадать при группировке
 *    и сортировке: «7» и «007» — разные строки одного и того же числа.
 * 2. Пересчёт отказывает, когда значения в новый тип не помещаются: молча
 *    сменить тип и оставить «Самая прочная» в целочисленном свойстве нельзя,
 *    иначе данные станут бессмысленными.
 */
class PropertyTypeTest extends TestCase
{
    public function test_text_is_kept_as_is_but_trimmed(): void
    {
        $this->assertSame('Самая прочная', PropertyType::String->normalize('  Самая прочная '));
    }

    public function test_integer_is_canonicalised(): void
    {
        $this->assertSame('7', PropertyType::Int->normalize('007'));
        $this->assertSame('7', PropertyType::Int->normalize('+7'));
        $this->assertSame('-7', PropertyType::Int->normalize('-7'));
        // Неразрывный пробел приходит из автозамены на телефоне и из вставки
        // из Word, а для разбора числа ничем не отличается от обычного.
        $this->assertSame('1000', PropertyType::Int->normalize("1\u{00A0}000"));
    }

    public function test_float_accepts_both_decimal_marks(): void
    {
        $this->assertSame('1.5', PropertyType::Float->normalize('1,50'));
        $this->assertSame('1.5', PropertyType::Float->normalize('1.5'));
        $this->assertSame('0.75', PropertyType::Float->normalize('0,75'));
    }

    public function test_float_of_whole_number_stays_whole(): void
    {
        // Целое остаётся целым и во дробном: «20.0» в тексте отличалось бы от
        // «20» при группировке и сортировке по значению.
        $this->assertSame('20', PropertyType::Float->normalize('20'));
        $this->assertSame('20', PropertyType::Int->normalize('20'));
    }

    public function test_boolean_accepts_every_spelling_people_type(): void
    {
        foreach (['да', 'ДА', 'yes', 'true', '1', 'on'] as $value) {
            $this->assertSame('да', PropertyType::Bool->normalize($value), $value);
        }

        foreach (['нет', 'НЕТ', 'no', 'false', '0', 'off'] as $value) {
            $this->assertSame('нет', PropertyType::Bool->normalize($value), $value);
        }
    }

    public function test_text_survives_a_change_to_a_numeric_type_when_it_is_a_number(): void
    {
        // Ровно тот случай, ради которого смена типа вообще возможна: в
        // текстовом свойстве набрали «10», и после смены на целое это то же
        // самое число в каноничной записи.
        $this->assertSame('10', PropertyType::Int->normalize('10'));
        $this->assertSame('10', PropertyType::Float->normalize('10'));
    }

    public function test_number_survives_a_change_back_to_text(): void
    {
        $this->assertSame('1.5', PropertyType::String->normalize('1.5'));
    }

    public function test_text_that_is_not_a_number_is_refused_by_numeric_types(): void
    {
        foreach ([PropertyType::Int, PropertyType::Float] as $type) {
            try {
                $type->normalize('Самая прочная');
                $this->fail($type->value . ' не должен принимать текст');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_fraction_is_refused_by_integer_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PropertyType::Int->normalize('1,5');
    }

    public function test_empty_value_is_refused_by_every_type(): void
    {
        // Пустое значение пересчитывать нечего, и новый тип не требует его
        // заполнять: такие строки смена типа пропускает, а не отвергает.
        foreach (PropertyType::cases() as $type) {
            try {
                $type->normalize('   ');
                $this->fail($type->value . ' не должен принимать пустое значение');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_dictionary_value_has_no_text_form(): void
    {
        // Значение из справочника лежит в dictionary_value_id, в текстовой
        // колонке его нет, поэтому и привести его не к чему.
        $this->expectException(InvalidArgumentException::class);

        PropertyType::Dictionary->normalize('Значение');
    }

    public function test_every_type_has_its_own_label(): void
    {
        $labels = array_map(fn (PropertyType $type): string => $type->label(), PropertyType::cases());

        $this->assertCount(count(PropertyType::cases()), array_unique($labels));
    }

    public function test_unit_makes_no_sense_for_a_dictionary(): void
    {
        $this->assertFalse(PropertyType::Dictionary->usesUnit());

        foreach (PropertyType::cases() as $type) {
            if ($type !== PropertyType::Dictionary) {
                $this->assertTrue($type->usesUnit(), $type->value);
            }
        }
    }
}
