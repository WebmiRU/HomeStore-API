<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Значение свойства раскладывается по типу: исходный ввод остаётся в value,
 * а рядом появляются generated-колонки, в которых Postgres сам считает то же
 * значение в виде, пригодном для чтения.
 *
 * Зачем это нужно. Тип у свойства один и общий для всех его значений, а
 * читать значения приходится по-разному: целое сравнивают и суммируют как
 * число, дробное — с дробной частью, «да/нет» — как признак, текст — как
 * есть. Раньше тип решал это единственным способом: значение нормализовалось
 * в текст при записи, и сменить тип у заполненного свойства было нельзя.
 * Теперь приведение делает сама база по колонке нужного типа, поэтому смена
 * типа — это только смена типа, а значения остаются как введены.
 *
 * Приведение в колонках повторяет PropertyType::normalize(), а не придумывает
 * своё: иначе одно и то же значение читалось бы в приложении и в отчётах по
 * разному. Поэтому «1,5» целым не становится, а нечисловой текст даёт NULL, а
 * не ноль.
 *
 * value_text — нормализованная текстовая запись без краевых пробелов. Исходный
 * ввод с автозаменой и лишними пробелами остаётся в value, а value_text —
 * это то, что сравнивают, группируют и ищут.
 *
 * value_bool читается терпимо: в да/нет люди пишут «да», «yes», «1», «on»,
 * и все эти записи означают одно и то же.
 *
 * Значения из справочника лежат в dictionary_value_id, и generated-колонки
 * для них остаются пустыми: у значения из справочника нет текстовой записи.
 */
return new class extends Migration
{
    /**
     * Число без пробелов и с точкой вместо запятой: неразрывный пробел
     * приходит из автозамены на телефоне и из вставки из Word, а для разбора
     * числа ничем от обычного не отличается, и в русской раскладке десятичный
     * знак — запятая («0,75»), поэтому она приводится к точке.
     *
     * Выражение собирается в методе, а не через str_replace по строке:
     * такой заменой по substrings из SQL-выражения вылетают и запятые между
     * аргументами replace(), и он перестаёт парситься.
     */
    private function number(): string
    {
        return "regexp_replace(replace(replace(value, chr(160), ' '), ' ', ''), ',', '.', 'g')";
    }

    public function up(): void
    {
        $number = $this->number();

        Schema::table('item_property', function (Blueprint $table) use ($number) {
            $table->text('value_text')->nullable()->storedAs(
                "nullif(btrim(replace(value, chr(160), ' ')), '')"
            );

            // Длина ограничена 18 цифрами: всё, что длиннее, в bigint не
            // помещается, и приведение упало бы на записи строки.
            $table->unsignedBigInteger('value_int')->nullable()->storedAs(
                "CASE WHEN {$number} ~ '^[+-]?[0-9]{1,18}$' THEN ({$number})::bigint END"
            );

            $table->double('value_float')->nullable()->storedAs(
                "CASE WHEN {$number} ~ '^[+-]?[0-9]+([.][0-9]+)?$' THEN ({$number})::double precision END"
            );

            $table->boolean('value_bool')->nullable()->storedAs(
                "CASE lower(btrim(replace(value, chr(160), ' ')))"
                . " WHEN 'да' THEN true WHEN 'yes' THEN true WHEN 'true' THEN true WHEN '1' THEN true WHEN 'on' THEN true"
                . " WHEN 'нет' THEN false WHEN 'no' THEN false WHEN 'false' THEN false WHEN '0' THEN false WHEN 'off' THEN false"
                . ' END'
            );
        });

        // Числовые свойства читают и сравнивают по своей колонке, и поиск по
        // значению идёт в паре со свойством: без индекса он сканировал бы всю
        // таблицу значений.
        DB::statement('CREATE INDEX item_property_property_value_int_index ON item_property (property_id, value_int)');
        DB::statement('CREATE INDEX item_property_property_value_float_index ON item_property (property_id, value_float)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS item_property_property_value_float_index');
        DB::statement('DROP INDEX IF EXISTS item_property_property_value_int_index');

        Schema::table('item_property', function (Blueprint $table) {
            $table->dropColumn(['value_text', 'value_int', 'value_float', 'value_bool']);
        });
    }
};
