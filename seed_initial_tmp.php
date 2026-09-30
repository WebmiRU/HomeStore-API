<?php

/**
 * Разовый залив: остатки, заведённые в карточках вручную, становятся начальными.
 *
 * Количество у предмета ставится в карточке, а журнал движений при этом не
 * пополняется — и в статистике остаток показывается пустым, будто предмета
 * на складе нет. Лечится одной операцией пополнения на предмет: остаток
 * становится виден, а «Вернуть» у такой операции работает как у любой.
 *
 * Предметы, у которых в журнале уже есть строки, не трогаем: у них история
 * есть, и подмена её началом с нуля исказила бы откаты.
 */

use App\Enums\StockDirection;
use App\Models\Item;
use App\Models\UserProfile;
use App\Services\StockOperationService;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\DB;

$actor = UserProfile::query()->orderBy('id')->first();

if (! $actor) {
    fwrite(STDERR, "нет ни одного пользователя\n");

    exit(1);
}

CurrentUser::set($actor);

$service = app(StockOperationService::class);

$items = Item::query()
    ->whereNull('deleted_at')
    ->where('quantity', '>', 0)
    ->orderBy('id')
    ->get();

fwrite(STDOUT, "предметов без движений: {$items->count()}\n");

$made = 0;
$skipped = 0;

foreach ($items as $item) {
    $already = DB::table('stock_operation_item')->where('item_id', $item->id)->exists();

    if ($already) {
        $skipped++;

        continue;
    }

    try {
        $service->record(
            StockDirection::Replenish,
            'Заведение карточки',
            [[
                'item_id' => $item->id,
                'title'   => $item->title,
                'delta'   => (int) $item->quantity,
                'before'  => 0,
                'after'   => (int) $item->quantity,
            ]],
        );

        $made++;
        fwrite(STDOUT, "#{$item->id} {$item->title} — {$item->quantity} шт\n");
    } catch (\Throwable $e) {
        fwrite(STDERR, "#{$item->id} {$item->title}: {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "создано операций: {$made}, пропущено: {$skipped}\n");