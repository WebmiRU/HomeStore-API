<?php

namespace Database\Seeders;

use App\Enums\PropertyType;
use App\Models\Category;
use App\Models\Dictionary;
use App\Models\DictionaryValue;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\Unit;
use App\Models\UserProfile;
use App\Support\CurrentUser;
use App\Support\DefaultUnits;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Каталог, свойства, единицы, справочники и заполненные значения для
 * демо-пользователя.
 *
 * Отдельный сидер, а не дополнение к DemoItemsSeeder: дерево категорий и
 * сами свойства нужны раньше первого предмета, а раскладывать предметы по
 * категориям можно только после того, как их завёл DemoItems*.Seeder.
 * Поэтому в DatabaseSeeder он стоит после них.
 *
 * Всё идемпотентно: сидер гоняют повторно на локальной базе, где демо-данные
 * уже есть.
 */
class DemoCatalogSeeder extends Seeder
{
    /**
     * id справочника => (название значения => id значения). Заполняется в
     * seedDictionaries(). Ключ составной по той же причине, по которой
     * справочники разведены по группам: «Нержавеющая сталь» и «Нержавеющее» —
     * разные значения разных справочников, и поиск только по названию
     * подставил бы id чужого.
     *
     * @var array<int, array<string, int>>
     */
    private array $dictionaryValues = [];

    public function run(): void
    {
        $demo = UserProfile::where('email', 'demo@demo.demo')->firstOrFail();

        // Скоупы OwnedByUser смотрят на CurrentUser: без него запросы видели бы
        // пустоту, и updateOrCreate нашёл бы «свою» строку у первого же
        // пользователя таблицы.
        CurrentUser::set($demo);

        $units = $this->seedUnits($demo);
        $groups = $this->seedGroups($demo);
        $dictionaries = $this->seedDictionaries($demo);
        $properties = $this->seedProperties($demo, $groups, $units, $dictionaries);
        $categories = $this->seedCategories($demo);

        $this->seedItemValues($properties, $categories);
    }

    /**
     * Единицы: те же четыре, что достаются новому пользователю при
     * регистрации, плюс грамм — на него в демо идут веса крепежа.
     *
     * @return array<string, Unit> короткая подпись => единица
     */
    private function seedUnits(UserProfile $user): array
    {
        // Грамм нужен только демо-крепежу (веса у болтов и гаек), в список по
        // умолчанию для нового пользователя он не входит. Именно array_merge,
        // а не «+»: у обоих массивов числовые ключи, и «+» оставил бы только
        // первые четыре элемента, молча потеряв грамм.
        $definitions = array_merge(
            DefaultUnits::definition(),
            [['title_short' => 'г', 'title_full' => 'грамм']],
        );

        $units = [];

        foreach ($definitions as $definition) {
            $unit = Unit::updateOrCreate(
                ['user_id' => $user->id, 'title_short' => $definition['title_short']],
                $definition + ['user_id' => $user->id],
            );

            $units[$unit->title_short] = $unit;
        }

        return $units;
    }

    /**
     * @return array<string, PropertyGroup>
     */
    private function seedGroups(UserProfile $user): array
    {
        $groups = [];

        foreach (['Размеры', 'Материал и покрытие', 'Прочее'] as $title) {
            $groups[$title] = PropertyGroup::updateOrCreate(
                ['user_id' => $user->id, 'title' => $title],
                ['user_id' => $user->id],
            );
        }

        return $groups;
    }

    /**
     * @return array<string, Dictionary>
     */
    private function seedDictionaries(UserProfile $user): array
    {
        $dictionaries = [];

        foreach (['Материал', 'Покрытие', 'Тип резьбы'] as $title) {
            $dictionaries[$title] = Dictionary::updateOrCreate(
                ['user_id' => $user->id, 'title' => $title],
                ['user_id' => $user->id],
            );
        }

        // Значения пересоздаём целиком: сидер должен приводить справочник к
        // актуальному составу, а дописывать к нему новые строки при каждом
        // прогоне. Пользовательские правки демо-сидер не трогает — он идёт
        // после миграции, до первого входа в интерфейс.
        $definitions = [
            'Материал' => ['Сталь', 'Нержавеющая сталь', 'Латунь', 'Алюминий', 'Пластик', 'Дерево'],
            'Покрытие' => ['Без покрытия', 'Оцинкованное', 'Жёлтое цинковое', 'Нержавеющее', 'Порошковое'],
            'Тип резьбы' => ['Метрическая', 'Дюймовая', 'Трапецеидальная', 'Витова', 'Без резьбы'],
        ];

        $byDictionary = [];

        foreach ($definitions as $dictionaryTitle => $titles) {
            $dictionaryId = $dictionaries[$dictionaryTitle]->id;
            $byDictionary[$dictionaryId] = [];

            foreach ($titles as $valueTitle) {
                $value = DictionaryValue::updateOrCreate(
                    ['dictionary_id' => $dictionaryId, 'title' => $valueTitle],
                    [],
                );

                $byDictionary[$dictionaryId][$valueTitle] = $value->id;
            }
        }

        $this->dictionaryValues = $byDictionary;

        return $dictionaries;
    }

    /**
     * @param  array<string, Unit>  $units
     * @param  array<string, PropertyGroup>  $groups
     * @param  array<string, Dictionary>  $dictionaries
     * @return array<string, Property>
     */
    private function seedProperties(
        UserProfile $user,
        array $groups,
        array $units,
        array $dictionaries,
    ): array {
        $definitions = [
            // Единица бывает только у числа, справочник — только у своего типа.
            // Это не соглашение сидера, а правила StorePropertyRequest.
            //
            // Диаметр дробный, а не целый: у самореза «4.2х16» и «3.5х25»
            // номинальный диаметр дробный, и целый тип молча срезал бы его до
            // 4 и 3. Целый тип показан отдельным свойством.
            'Диаметр' => ['type' => PropertyType::Float, 'group' => 'Размеры', 'unit' => 'мм'],
            'Длина' => ['type' => PropertyType::Float, 'group' => 'Размеры', 'unit' => 'мм'],
            'Вес' => ['type' => PropertyType::Float, 'group' => 'Размеры', 'unit' => 'г'],
            'В упаковке' => ['type' => PropertyType::Int, 'group' => 'Размеры', 'unit' => 'шт'],
            'Материал' => ['type' => PropertyType::Dictionary, 'group' => 'Материал и покрытие', 'dictionary' => 'Материал'],
            'Покрытие' => ['type' => PropertyType::Dictionary, 'group' => 'Материал и покрытие', 'dictionary' => 'Покрытие'],
            'Тип резьбы' => ['type' => PropertyType::Dictionary, 'group' => 'Прочее', 'dictionary' => 'Тип резьбы'],
            'Класс прочности' => ['type' => PropertyType::String, 'group' => 'Прочее'],
            'Самоконтрящаяся' => ['type' => PropertyType::Bool, 'group' => 'Прочее'],
        ];

        $properties = [];

        foreach ($definitions as $title => $definition) {
            $attributes = [
                'user_id' => $user->id,
                'type' => $definition['type']->value,
                'group_id' => isset($definition['group']) ? $groups[$definition['group']]->id : null,
                'unit_id' => isset($definition['unit']) ? $units[$definition['unit']]->id : null,
                'dictionary_id' => isset($definition['dictionary'])
                    ? $dictionaries[$definition['dictionary']]->id
                    : null,
            ];

            $properties[$title] = Property::updateOrCreate(
                ['user_id' => $user->id, 'title' => $title],
                $attributes,
            );
        }

        return $properties;
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(UserProfile $user): array
    {
        $categories = [];

        $fasteners = Category::updateOrCreate(
            ['user_id' => $user->id, 'parent_id' => null, 'title' => 'Крепёж'],
            ['user_id' => $user->id],
        );
        $categories['Крепёж'] = $fasteners;

        foreach (['Болты', 'Гайки', 'Шайбы', 'Саморезы и шурупы', 'Анкеры и дюбели'] as $title) {
            $categories[$title] = Category::updateOrCreate(
                ['user_id' => $user->id, 'parent_id' => $fasteners->id, 'title' => $title],
                ['user_id' => $user->id],
            );
        }

        // Корневые категории, у которых предметов нет, — и одна вложенная под
        // «Электрикой». Их набор свойств пуст, и это ровно тот случай, из-за
        // которого набор и считается по заполненным значениям, а не хранится
        // в связях.
        foreach (['Электрика' => ['Кабели', 'Питание'], 'Канцелярия' => [], 'Разное' => []] as $title => $children) {
            $parent = Category::updateOrCreate(
                ['user_id' => $user->id, 'parent_id' => null, 'title' => $title],
                ['user_id' => $user->id],
            );
            $categories[$title] = $parent;

            foreach ($children as $childTitle) {
                $categories[$childTitle] = Category::updateOrCreate(
                    ['user_id' => $user->id, 'parent_id' => $parent->id, 'title' => $childTitle],
                    ['user_id' => $user->id],
                );
            }
        }

        return $categories;
    }

    /**
     * Раскладывает демо-предметы по категориям и заполняет их свойства.
     *
     * @param  array<string, Property>  $properties
     * @param  array<string, Category>  $categories
     */
    private function seedItemValues(array $properties, array $categories): void
    {
        foreach ($this->items() as $title => $spec) {
            $item = Item::query()->where('title', $title)->first();

            if ($item === null) {
                // Предмет завозится DemoItems*Seeder. Молча пропускаем, а не
                // падаем: о неверном порядке вызова честнее сказать
                // сообщением, чем «сломан каталог».
                continue;
            }

            $item->update(['category_id' => $categories[$spec[0]]->id]);

            ItemProperty::query()->where('item_id', $item->id)->delete();

            $sort = 0;

            foreach ($spec[1] as $propertyTitle => $values) {
                $property = $properties[$propertyTitle];
                $isDictionary = $property->type === PropertyType::Dictionary;

                foreach ((array) $values as $value) {
                    DB::table('item_property')->insert([
                        'item_id' => $item->id,
                        'property_id' => $property->id,
                        'value' => $isDictionary ? null : (string) $value,
                        'dictionary_value_id' => $isDictionary
                            ? $this->dictionaryValues[$property->dictionary_id][$value]
                            : null,
                        'sort' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Демо-предметы: категория и значения свойств.
     *
     * Размеры выписаны по одному разбору названия, а не выведены из него
     * регуляркой: у «Саморез 4.2х16 с прессшайбой» и «Дюбель-гвоздь» разбор
     * молча дал бы половину результата, и демо-данные в этом виде всё равно
     * пришлось бы сверять глазами.
     *
     * @return array<string, array{0: string, 1: array<string, string|int|float>}>
     */
    private function items(): array
    {
        $items = [];

        $add = function (string $title, string $category, array $properties) use (&$items): void {
            $items[$title] = [$category, $properties];
        };

        $steel = ['Материал' => 'Сталь', 'Покрытие' => 'Оцинкованное'];

        // Болты: [размер в названии, диаметр, длина, вес, класс, штук в упаковке]
        foreach ([
            ['М6х10', 6.0, 10.0, 4.4, '8.8', 100],
            ['М8х25', 8.0, 25.0, 12.2, '8.8', 50],
            ['М10х30', 10.0, 30.0, 24.0, '10.9', 25],
        ] as [$title, $d, $l, $weight, $class, $pack]) {
            $add("Болт {$title}", 'Болты', $steel + [
                'Диаметр' => $d,
                'Длина' => $l,
                'Вес' => $weight,
                'В упаковке' => $pack,
                'Класс прочности' => $class,
                'Тип резьбы' => 'Метрическая',
            ]);
        }

        // Гайки
        foreach ([['М6', 6.0, 3.2, 50], ['М8', 8.0, 6.0, 25], ['М10', 10.0, 12.0, 20]] as [$title, $d, $weight, $pack]) {
            $add("Гайка {$title}", 'Гайки', $steel + [
                'Диаметр' => $d,
                'Вес' => $weight,
                'В упаковке' => $pack,
                'Класс прочности' => '8.8',
                'Тип резьбы' => 'Метрическая',
                'Самоконтрящаяся' => 'нет',
            ]);
        }

        $add('Гайка самоконтрящаяся', 'Гайки', $steel + [
            'Тип резьбы' => 'Метрическая',
            'Самоконтрящаяся' => 'да',
        ]);

        // Шайбы
        $add('Шайба плоская', 'Шайбы', $steel + ['Тип резьбы' => 'Без резьбы']);
        $add('Шайба гровер', 'Шайбы', [
            'Материал' => 'Нержавеющая сталь',
            'Покрытие' => 'Нержавеющее',
            'Тип резьбы' => 'Без резьбы',
        ]);
        $add('Шайба пружинная', 'Шайбы', $steel + ['Тип резьбы' => 'Без резьбы']);

        // Саморезы и шурупы: номинальный диаметр дробный, поэтому «Диаметр»
        // и объявлен дробным — иначе значение срезалось бы до целого.
        foreach ([
            ['Саморез 3.5х25', 3.5, 25.0],
            ['Саморез 4х35', 4.0, 35.0],
            ['Саморез 4.2х16 с прессшайбой', 4.2, 16.0],
        ] as [$title, $d, $l]) {
            $add($title, 'Саморезы и шурупы', $steel + [
                'Диаметр' => $d,
                'Длина' => $l,
                'В упаковке' => 200,
                'Тип резьбы' => 'Без резьбы',
            ]);
        }

        $add('Шуруп 5х50', 'Саморезы и шурупы', $steel + [
            'Диаметр' => 5.0,
            'Длина' => 50.0,
            'В упаковке' => 100,
            'Тип резьбы' => 'Без резьбы',
        ]);
        $add('Шуруп самонарезающий', 'Саморезы и шурупы', $steel + ['Тип резьбы' => 'Без резьбы']);

        // Анкеры и дюбели
        $add('Анкер клиновой', 'Анкеры и дюбели', $steel + [
            'Класс прочности' => '8.8',
            'Тип резьбы' => 'Метрическая',
        ]);
        $add('Дюбель 6х30', 'Анкеры и дюбели', [
            'Материал' => 'Пластик',
            'Диаметр' => 6.0,
            'Длина' => 30.0,
            'В упаковке' => 50,
            'Тип резьбы' => 'Без резьбы',
        ]);
        $add('Дюбель-гвоздь', 'Анкеры и дюбели', ['Материал' => 'Пластик', 'Тип резьбы' => 'Без резьбы']);
        $add('Дюбель-бабочка', 'Анкеры и дюбели', ['Материал' => 'Пластик', 'Тип резьбы' => 'Без резьбы']);

        // Винты, шпильки, хомуты и прочее
        foreach ([['Винт М3х10', 3.0, 10.0], ['Винт М4х20', 4.0, 20.0]] as [$title, $d, $l]) {
            $add($title, 'Крепёж', $steel + [
                'Диаметр' => $d,
                'Длина' => $l,
                'В упаковке' => 100,
                'Тип резьбы' => 'Метрическая',
            ]);
        }

        $add('Шпилька резьбовая', 'Крепёж', $steel + ['Тип резьбы' => 'Метрическая']);
        $add('Гвоздь строительный', 'Крепёж', ['Материал' => 'Сталь', 'Тип резьбы' => 'Без резьбы']);
        $add('Хомут червячный', 'Крепёж', [
            'Материал' => 'Нержавеющая сталь',
            'Тип резьбы' => 'Метрическая',
        ]);
        $add('Стяжка нейлоновая', 'Крепёж', ['Материал' => 'Пластик']);
        $add('Заклёпка вытяжная', 'Крепёж', ['Материал' => 'Алюминий']);

        // Канцелярия и электрика — с единственным осмысленным значением.
        foreach ([
            'Кабель USB' => ['Электрика' => 'Кабели'],
            'Кабель HDMI' => ['Электрика' => 'Кабели'],
            'Флешка USB' => ['Электрика' => 'Кабели'],
            'Зарядное устройство' => ['Электрика' => 'Питание'],
            'Батарейки AA' => ['Электрика' => 'Питание'],
            'Батарейки AAA' => ['Электрика' => 'Питание'],
        ] as $title => $category) {
            $add($title, array_key_first($category), []);
        }

        foreach ([
            'Скрепки канцелярские', 'Карандаш простой', 'Ручка шариковая',
            'Маркер перманентный', 'Ластик', 'Стикеры', 'Ножницы', 'Степлер',
            'Скобы для степлера', 'Линейка', 'Клей-карандаш', 'Канцелярский нож',
            'Кнопки канцелярские', 'Булавки',
        ] as $title) {
            $add($title, 'Канцелярия', []);
        }

        foreach (['Изолента', 'Скотч', 'Отвёртка', 'Пинцет'] as $title) {
            $add($title, 'Разное', []);
        }

        return $items;
    }
}
