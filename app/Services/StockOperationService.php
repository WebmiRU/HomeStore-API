<?php

namespace App\Services;

use App\Enums\StockDirection;
use App\Models\Item;
use App\Models\StockOperation;
use App\Models\StockOperationItem;
use App\Support\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Проведение складских операций и их откатов.
 *
 * Ключевое правило: откат применяет ОБРАТНУЮ ДЕЛЬТУ, а не возвращает снимок
 * before. Списали 10 (10→0), пополнили 5 (0→5), откатываем списание — остаток
 * должен стать 15, а не 10: возврат снимка затёр бы последующее пополнение.
 */
class StockOperationService
{
    public function __construct(private readonly WriteoffCodeRelease $releasedCodes)
    {
    }

    /**
     * Сохраняет уже применённую операцию в журнал движений.
     *
     * Вызывается после того, как остатки уже изменены (см. OperationController):
     * повторное применение дельт здесь было бы второй транзакцией с теми же
     * числами — источником расхождений при частичном откате.
     *
     * У строки частичного расхода amount задан, а quantity равно нулю либо
     * числу списавшихся штук: списание 300 мл из бутылки не уменьшает
     * количество предмета, но всё равно попадает в журнал.
     *
     * @param  array<int, array{code: string, item_id: int, title: string, delta: int, before: int, after: int, owner_id?: int|null, released_code_id?: int|null, property_id?: int|null, property_title?: string|null, amount?: float|null, property_before?: float|null, property_after?: float|null, is_writeoff?: bool}>  $appliedRows
     */
    public function record(StockDirection $direction, ?string $comment, array $appliedRows): StockOperation
    {
        return DB::transaction(function () use ($direction, $comment, $appliedRows): StockOperation {
            $operation = StockOperation::create([
                'user_id'    => CurrentUser::id(),
                'direction'  => $direction->value,
                'comment'    => $comment !== null && $comment !== '' ? $comment : null,
            ]);

            foreach ($appliedRows as $row) {
                $isPartial = isset($row['amount']);

                $operation->rows()->create([
                    'item_id'     => $row['item_id'],
                    'owner_id'    => $row['owner_id'] ?? null,
                    'item_title'  => $row['title'],
                    // У обычной строки quantity всегда положителен, у строки
                    // частичного расхода штук могло не уйти вовсе — и там
                    // quantity честно ноль, а расход лежит в amount.
                    'quantity'    => $isPartial
                        ? abs((int) $row['delta'])
                        : max(1, (int) $row['delta']),
                    'before'      => (int) $row['before'],
                    'after'       => (int) $row['after'],

                    // Расход по свойству: сколько было, сколько забрали и
                    // сколько осталось. Откат возвращает расход по этим же
                    // числам, поэтому журнал должен их хранить.
                    'property_id'      => $row['property_id'] ?? null,
                    'property_title'   => $row['property_title'] ?? null,
                    'amount'           => $isPartial ? (float) $row['amount'] : null,
                    'property_before'  => $row['property_before'] ?? null,
                    'property_after'   => $row['property_after'] ?? null,

                    // Код, высвобождённый списанием по коду: по нему откат
                    // вернёт код предмету.
                    'released_code_id' => $row['released_code_id'] ?? null,
                ]);
            }

            return $operation->refresh();
        });
    }

    /**
     * Откат операции — полный или частичный.
     *
     * Частичным может быть и набор строк («болты вернули, футболки оставили»),
     * и количество внутри строки («вернули 4 из 10»). Возвращать больше, чем
     * списано, нельзя: проверяем и остаток по строке, и наличие товара на
     * складе, когда возврат означает списание.
     *
     * @param  array<int, array{row_id: int, quantity: int}>  $selections
     */
    /**
     * Возврат частичного расхода.
     *
     * Расход возвращается ровно тем же расчётом, каким снимался, только в
     * обратную сторону: возвращается та же доля по тому же свойству, и
     * опустевшая штука возвращается целой, а новая полная уходит. Возвращать
     * снимок «было 1000» нельзя — после списания бутылку могли пополнить, и
     * снимок затёр бы это пополнение.
     *
     * @return array<string, mixed> строка возврата
     */
    private function reversePartialRow(
        Item $item,
        StockOperationItem $row,
        float $amount,
        ?int $ownerId,
        StockDirection $reversalDirection,
    ): array {
        $partial = app(PartialWriteoff::class);
        $propertyId = (int) $row->property_id;
        $settings = $partial->settings($item);

        $before = (int) ($item->quantity ?? 0);
        $norm = (float) $partial->norm($item, $propertyId);
        $propertyBefore = $partial->total($item, $propertyId, $norm);

        // Направление берётся у самой операции: возврат списания — это
        // пополнение, возврат пополнения — списание. Угадывать по числам
        // строки нельзя: у бутылки списали 300 мл, её пополнили на 100 и снова
        // списали 200 — состояния одинаковые, а откат третьей строки должен
        // вернуть 200 мл, а не наоборот отнять.
        $result = $reversalDirection === StockDirection::Replenish
            ? $partial->replenish($item, $propertyId, $amount, $settings)
            : $partial->spend($item, $propertyId, $amount, $settings);

        $partial->persist($item, $result['remainders'], (int) $result['quantity']);

        $item->refresh();

        $row->increment('reversed_amount', $amount);

        return [
            'item_id'         => $item->id,
            'owner_id'        => $ownerId,
            'item_title'      => $row->item_title,
            'quantity'        => 0,
            'before'          => $before,
            'after'           => (int) $item->quantity,
            'source_row_id'   => $row->id,
            'property_id'     => $propertyId,
            'property_title'  => $row->property_title,
            'amount'          => $amount,
            'property_before' => $propertyBefore,
            'property_after'  => $partial->total($item, $propertyId, $norm),
        ];
    }

    /** Человеческое число в сообщении: 900, а не 900.0000000001. */
    private function formatAmount(float $amount): string
    {
        $rounded = round($amount, 3);

        return rtrim(rtrim(number_format($rounded, 3, ',', ' '), '0'), ',');
    }

    public function reverse(StockOperation $original, array $selections, ?string $comment = null): StockOperation
    {
        if ($original->isReversal()) {
            throw ValidationException::withMessages([
                'operation' => [__('Откат является возвратом — откатывать его нельзя')],
            ]);
        }

        $original->loadMissing('rows');

        $userId = CurrentUser::id();

        // Проход 1: собираем все проблемы разом, чтобы не «падать» по первой.
        $errors = [];
        $prepared = [];
        $seen = [];

        foreach ($selections as $selection) {
            $rowId = (int) ($selection['row_id'] ?? 0);
            $quantity = (int) ($selection['quantity'] ?? 0);

            if ($rowId === 0) {
                $errors[] = 'Не указана строка операции';
                continue;
            }

            /** @var ?StockOperationItem $row */
            $row = $original->rows->firstWhere('id', $rowId);

            if ($row === null) {
                $errors[] = sprintf('Строка №%d не относится к операции №%d', $rowId, $original->id);
                continue;
            }

            if ($userId !== null && $row->owner_id !== $userId) {
                $errors[] = sprintf('Строка «%s» вам не принадлежит', $row->item_title);
                continue;
            }

            if (isset($seen[$rowId])) {
                $errors[] = sprintf('Строка «%s» указана дважды — объедините количество', $row->item_title);
                continue;
            }

            $seen[$rowId] = true;

            $isPartial = $row->isPartial();

            if ($isPartial) {
                // Возврат частичного расхода измеряется тем же, чем списание:
                // долями свойства. Принимаем дробное, но не ноль — вернуть
                // «ноль миллилитров» нельзя, это не возврат.
                $amount = (float) ($selection['amount'] ?? $selection['quantity'] ?? 0);
            } else {
                $amount = (float) $quantity;

                if ($quantity < 1) {
                    $errors[] = sprintf('Строка «%s»: количество возврата должно быть больше нуля', $row->item_title);
                    continue;
                }
            }

            if ($amount <= 0) {
                $errors[] = sprintf('Строка «%s»: количество возврата должно быть больше нуля', $row->item_title);
                continue;
            }

            $remaining = $row->remaining();

            if ($amount - $remaining > PartialWriteoff::TOLERANCE) {
                $errors[] = $isPartial
                    ? sprintf(
                        'Строка «%s»: вернуть можно не больше %s (возвращено %s из %s)',
                        $row->item_title,
                        $this->formatAmount($remaining),
                        $this->formatAmount((float) $row->reversed_amount),
                        $this->formatAmount((float) $row->amount)
                    )
                    : sprintf(
                        'Строка «%s»: вернуть можно не больше %d шт. (возвращено %d из %d)',
                        $row->item_title,
                        $remaining,
                        $row->reversed_quantity,
                        $row->quantity
                    );
                continue;
            }

            if ($row->item_id === null || Item::find($row->item_id) === null) {
                $errors[] = sprintf('Предмет «%s» удалён — вернуть его движение нельзя', $row->item_title);
                continue;
            }

            if ($isPartial) {
                // Настройку расхода частями могли снять уже после списания, и
                // свойство — удалить. Возвращать расход тогда не во что:
                // предмет снова обычный, и доли свойства ему не принадлежит.
                $partial = app(PartialWriteoff::class);
                $item = Item::findOrFail($row->item_id);

                if ($partial->settings($item)->isEmpty() || $partial->norm($item, (int) $row->property_id) === null) {
                    $errors[] = sprintf(
                        'Строка «%s»: расход частями у предмета больше не настроен — вернуть его нельзя',
                        $row->item_title
                    );
                    continue;
                }
            }

            $prepared[] = ['row' => $row, 'quantity' => $quantity, 'amount' => $amount];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['operation' => $errors]);
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'operation' => [__('Не выбрано ни одной строки для возврата')],
            ]);
        }

        $direction = $original->direction->opposite();

        return DB::transaction(function () use ($original, $prepared, $direction, $comment): StockOperation {
            $reversal = StockOperation::create([
                'user_id'               => CurrentUser::id(),
                'direction'             => $direction->value,
                'comment'               => $comment !== null && $comment !== ''
                    ? $comment
                    : sprintf('Возврат операции №%d', $original->id),
                'reversed_operation_id' => $original->id,
            ]);

            $sign = $direction->sign();

            foreach ($prepared as $entry) {
                /** @var StockOperationItem $row */
                $row = $entry['row'];
                $quantity = (int) $entry['quantity'];
                $item = Item::findOrFail($row->item_id);

                if ($row->isPartial()) {
                    $reversal->rows()->create(
                        $this->reversePartialRow(
                            $item,
                            $row,
                            (float) $entry['amount'],
                            $row->owner_id,
                            $direction
                        )
                    );

                    continue;
                }

                // Пустое количество — это не ноль, а «без количества»: предмет,
                // которого на складе одна штука и который поштучно не считают.
                // Поэтому при возврате оно читается как одна штука, а не как
                // ноль: вернуть единицу к монолиту — это уже две. Записано
                // после этого будет число, потому что одна штука поштучно не
                // считается.
                $before = $item->quantity === null ? 1 : (int) $item->quantity;
                $after = $before + $sign * $quantity;

                $item->update(['quantity' => $after]);

                $reversal->rows()->create([
                    'item_id'       => $item->id,
                    'owner_id'      => $row->owner_id,
                    'item_title'    => $row->item_title,
                    'source_row_id' => $row->id,
                    'quantity'      => $quantity,
                    'before'        => $before,
                    'after'         => $after,
                ]);

                $row->increment('reversed_quantity', $quantity);
            }

            // Коды, высвобождённые списанием, возвращаются предмету: товар
            // вернули, упаковку вскрыли, и код снова на месте. Возвращаются
            // только коды тех строк, которые этой операцией откатываются.
            $this->releasedCodes->restore($prepared);

            $original->forceFill(['reversed_at' => now()])->save();

            return $reversal->refresh();
        });
    }
}
