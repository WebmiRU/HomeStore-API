<?php

namespace App\Services;

use App\Enums\StockDirection;
use App\Models\Item;
use App\Models\Property;

/**
 * Заведение карточки как приход остатка.
 *
 * Число, указанное в карточке при создании, — это не описание предмета, а
 * появление товара на складе. Пока оно было просто полем в таблице, о нём не
 * оставалось следа: в графике остатков не было стартовой точки, в журнале
 * движений — ничего, и вопрос «откуда взялось 3000 мл» не имел ответа.
 *
 * Поэтому карточка заводит приход: столько штук, сколько указано, и по
 * нормам расходуемых свойств — столько, сколько набирается на это количество.
 * Всё одной операцией, в той же транзакции, что и создание карточки: предмет
 * без единого движения остатка в журнале означал бы ровно то, от чего мы
 * уходим.
 *
 * Количество не указано — прихода нет. Отсутствие числа в карточке означает
 * единичный экземпляр, а единичный экземпляр не появляется на складе, он
 * просто есть.
 */
class InitialStock
{
    public function __construct(
        private readonly PartialWriteoff $partial,
        private readonly StockOperationService $operations,
    ) {}

    /**
     * Записать приход, если количество задано.
     *
     * @param  int|null  $quantity  количество из карточки; null — единичный экземпляр
     * @return \App\Models\StockOperation|null
     */
    public function recordFor(Item $item, ?int $quantity): ?\App\Models\StockOperation
    {
        if ($quantity === null || $quantity <= 0) {
            return null;
        }

        $rows = $this->rowsFor($item, $quantity);

        if ($rows === []) {
            return null;
        }

        return $this->operations->record(
            StockDirection::Replenish,
            __('Заведение карточки'),
            $rows,
        );
    }

    /**
     * Строки прихода.
     *
     * По одной на каждый расходуемый свойство — там остаток и есть содержание
     * предмета, а количество штук считается из него. Обычному предмету, у
     * которого расходуемых свойств нет, идёт одна строка на штуки.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(Item $item, int $quantity): array
    {
        $rows = [];
        $code = $item->codes()->orderBy('id')->value('code');

        foreach ($this->partial->settings($item) as $setting) {
            $propertyId = (int) $setting->property_id;
            $norm = $this->partial->norm($item, $propertyId);

            // Без нормы расходовать нечего: с такими настройками остатка нет.
            if ($norm === null || $norm <= 0) {
                continue;
            }

            $amount = $norm * $quantity;

            $rows[] = [
                'code'            => (string) $code,
                'item_id'         => $item->id,
                'owner_id'        => $item->user_id,
                'title'           => $item->title,
                // Штуки при заведении не уходят и не приходят: они появляются
                // вместе с остатком, а расходуемое здесь — содержимое.
                'delta'           => 0,
                'before'          => 0,
                'after'           => $quantity,
                'property_id'     => $propertyId,
                'property_title'  => Property::find($propertyId)?->title ?? (string) $propertyId,
                'amount'          => $amount,
                'property_before' => 0.0,
                'property_after'  => $amount,
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'code'     => (string) $code,
                'item_id'  => $item->id,
                'owner_id' => $item->user_id,
                'title'    => $item->title,
                'delta'    => $quantity,
                'before'   => 0,
                'after'    => $quantity,
            ];
        }

        return $rows;
    }

    /**
     * Провести изменение количества обычного предмета.
     *
     * Правка количества — это тоже движение остатка: было 3, стало 5 — значит
     * пришли две штуки. Без операции в журнале оставалась бы правка карточки,
     * неотличимая от списания, а на графике — скачок без причины.
     *
     * У расходуемого предмета количество не правится: оно считается из
     * остатка свойств, и правка была бы правдой, которую сервер всё равно
     * пересчитывает по-своему. Метод поэтому откажется, а не промолчит.
     *
     * @return \App\Models\StockOperation|null
     */
    public function recordQuantityChange(Item $item, int $from, int $to, ?string $comment = null): ?\App\Models\StockOperation
    {
        if ($this->partial->settings($item)->isNotEmpty()) {
            return null;
        }

        $delta = $to - $from;

        if ($delta === 0) {
            return null;
        }

        $code = $item->codes()->orderBy('id')->value('code');

        return $this->operations->record(
            $delta > 0 ? StockDirection::Replenish : StockDirection::Writeoff,
            $comment ?? __('Изменение количества'),
            [[
                'code'     => (string) $code,
                'item_id'  => $item->id,
                'owner_id' => $item->user_id,
                'title'    => $item->title,
                'delta'    => abs($delta),
                'before'   => $from,
                'after'    => $to,
            ]],
        );
    }
}
