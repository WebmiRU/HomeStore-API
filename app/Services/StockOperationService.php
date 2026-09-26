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
    /**
     * Сохраняет уже применённую операцию в журнал движений.
     *
     * Вызывается после того, как остатки уже изменены (см. OperationController):
     * повторное применение дельт здесь было бы второй транзакцией с теми же
     * числами — источником расхождений при частичном откате.
     *
     * @param  array<int, array{code: string, item_id: int, title: string, delta: int, before: int, after: int, owner_id?: int|null}>  $appliedRows
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
                $operation->rows()->create([
                    'item_id'     => $row['item_id'],
                    'owner_id'    => $row['owner_id'] ?? null,
                    'item_title'  => $row['title'],
                    'quantity'    => max(1, (int) $row['delta']),
                    'before'      => (int) $row['before'],
                    'after'       => (int) $row['after'],
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
    public function reverse(StockOperation $original, array $selections, ?string $comment = null): StockOperation
    {
        if ($original->isReversal()) {
            throw ValidationException::withMessages([
                'operation' => ['Откат является возвратом — откатывать его нельзя'],
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

            if ($quantity < 1) {
                $errors[] = sprintf('Строка «%s»: количество возврата должно быть больше нуля', $row->item_title);
                continue;
            }

            $remaining = $row->remaining();

            if ($quantity > $remaining) {
                $errors[] = sprintf(
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

            $prepared[] = ['row' => $row, 'quantity' => $quantity];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['operation' => $errors]);
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'operation' => ['Не выбрано ни одной строки для возврата'],
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

                $before = (int) ($item->quantity ?? 0);
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

            $original->forceFill(['reversed_at' => now()])->save();

            return $reversal->refresh();
        });
    }
}
